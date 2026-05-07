<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RequestVerificationRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateAccountSecurityRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Requests\VerifyMfaRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\User;
use App\Models\UserAccessLog;
use App\Services\AuditTrail;
use App\Services\CdnFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly CdnFileUploadService $cdnFileUploadService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->auditTrail->record($request, 'auth.login_failed', metadata: [
                'email' => $credentials['email'],
            ]);

            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        return $this->tokenResponse($user, $request, 'auth.login');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->profile()->create([
            'initials' => $this->initialsFor($data['name']),
            'headline' => 'Membro da República',
        ]);
        $user->preferences()->create();
        $user->roleRecord()->create(['role' => 'membro']);
        $user->verification()->create(['status' => 'none']);

        return $this->tokenResponse($user, $request, 'auth.register', 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => SessionUserResource::make($request->user()),
        ]);
    }

    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $userMap = [
            'name' => 'name',
            'email' => 'email',
        ];
        $profileMap = [
            'headline' => 'headline',
            'bio' => 'bio',
            'avatarUrl' => 'avatar_url',
            'phone' => 'phone',
            'language' => 'language',
        ];
        $preferenceMap = [
            'publicProfile' => 'public_profile',
            'showEmail' => 'show_email',
            'searchEngineIndex' => 'search_engine_index',
            'allowMessages' => 'allow_messages',
            'showActivity' => 'show_activity',
        ];

        foreach ($userMap as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $user->{$column} = $data[$requestKey];
            }
        }

        $profile = $user->profile()->firstOrCreate([], [
            'initials' => $this->initialsFor($user->name),
            'headline' => 'Membro da República',
        ]);
        foreach ($profileMap as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $profile->{$column} = $data[$requestKey];
            }
        }

        if (array_key_exists('name', $data)) {
            $profile->initials = $this->initialsFor($data['name']);
        }

        $preferences = $user->preferences()->firstOrCreate();
        foreach ($preferenceMap as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $preferences->{$column} = $data[$requestKey];
            }
        }

        $user->save();
        $profile->save();
        $preferences->save();

        $this->auditTrail->record($request, 'account.updated', $user, metadata: [
            'fields' => array_keys($data),
        ]);

        return response()->json([
            'user' => SessionUserResource::make($user->refresh()->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog', 'mfaMethods'])),
        ]);
    }

    public function requestVerification(RequestVerificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $user->verification()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'pending',
                'requested_role' => $data['requestedRole'],
                'document' => $data['document'],
                'submitted_at' => now(),
            ],
        );

        $this->auditTrail->record($request, 'account.verification_requested', $user, metadata: [
            'requestedRole' => $data['requestedRole'],
        ]);

        return response()->json([
            'user' => SessionUserResource::make($user->refresh()->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog', 'mfaMethods'])),
        ]);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $uploaded = $this->cdnFileUploadService->uploadAndStore($request->file('file'));

        $baseUrl = rtrim((string) config('services.cdn_upload.base_url'), '/');
        $publicUrl = '/'.ltrim((string) $uploaded->public_url, '/');
        $avatarUrl = $baseUrl.$publicUrl;

        $profile = $user->profile()->firstOrCreate([], [
            'initials' => $this->initialsFor($user->name),
            'headline' => 'Membro da República',
        ]);
        $profile->avatar_url = $avatarUrl;
        $profile->save();

        $this->auditTrail->record($request, 'account.avatar_uploaded', $user, metadata: [
            'fileId' => $uploaded->external_file_id,
        ]);

        return response()->json([
            'avatarUrl' => $avatarUrl,
            'file' => [
                'id' => $uploaded->id,
                'externalFileId' => $uploaded->external_file_id,
                'originalFilename' => $uploaded->original_filename,
                'publicUrl' => $uploaded->public_url,
                'mimeType' => $uploaded->mime_type,
                'size' => $uploaded->size,
            ],
        ], 201);
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        $sessions = $request->user()
            ->tokens()
            ->latest()
            ->get()
            ->map(fn ($token): array => [
                'id' => (string) $token->id,
                'name' => $token->name,
                'device' => $this->auditTrail->deviceFromAgent($token->user_agent),
                'browser' => $this->auditTrail->browserFromAgent($token->user_agent),
                'ip' => $token->last_used_ip_address ?? $token->ip_address,
                'location' => null,
                'startedAt' => $token->created_at?->toISOString(),
                'lastUsedAt' => $token->last_used_at?->toISOString(),
                'current' => $token->id === $currentTokenId,
            ])
            ->values();

        return response()->json(['sessions' => $sessions]);
    }

    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokenActivity = $user->tokens()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($token): array => [
                'id' => 'token-'.$token->id,
                'action' => $token->id === $user->currentAccessToken()?->id ? 'Sessão atual' : 'Sessão criada',
                'detail' => $this->auditTrail->deviceFromAgent($token->user_agent),
                'at' => $token->created_at?->toISOString(),
            ]);
        $auditActivity = UserAccessLog::query()
            ->where('target_user_id', $user->id)
            ->where('event', '!=', 'api.access')
            ->latest('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn (UserAccessLog $log): array => [
                'id' => $log->id,
                'action' => $this->labelForAuditEvent($log->event),
                'detail' => $log->ip_address,
                'at' => $log->occurred_at?->toISOString(),
            ]);

        return response()->json([
            'history' => collect([
                [
                    'id' => 'account-created',
                    'action' => 'Conta criada',
                    'detail' => $user->email,
                    'at' => $user->created_at?->toISOString(),
                ],
            ])->merge($auditActivity)->merge($tokenActivity)->values(),
        ]);
    }

    public function destroySession(Request $request, string $tokenId): JsonResponse
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        if ((string) $currentTokenId === $tokenId) {
            return response()->json([
                'message' => 'A sessão atual não pode ser encerrada por esta ação.',
            ], 422);
        }

        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        if ($deleted > 0) {
            $this->auditTrail->record($request, 'auth.session_revoked', $request->user(), metadata: [
                'tokenId' => $tokenId,
            ]);
        }

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function destroyOtherSessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        $deleted = $request->user()
            ->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        $this->auditTrail->record($request, 'auth.other_sessions_revoked', $request->user(), metadata: [
            'deleted' => $deleted,
        ]);

        return response()->json(['message' => 'Outras sessões encerradas.']);
    }

    public function updateSecurity(UpdateAccountSecurityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $hasPasswordChange = array_key_exists('newPassword', $data);

        if ($hasPasswordChange && $data['newPassword'] !== ($data['newPasswordConfirmation'] ?? null)) {
            throw ValidationException::withMessages([
                'newPasswordConfirmation' => ['A confirmação da nova senha não confere.'],
            ]);
        }

        if ($hasPasswordChange) {
            if (! Hash::check($data['currentPassword'], $user->password)) {
                throw ValidationException::withMessages([
                    'currentPassword' => ['A senha atual informada é inválida.'],
                ]);
            }
            $user->password = $data['newPassword'];
            $user->save();
        }

        $preferences = $user->preferences()->firstOrCreate();

        if (array_key_exists('mfaEnabled', $data)) {
            $method = $data['mfaMethod'] ?? 'totp';
            $mfaRecord = $user->mfaMethods()->firstOrCreate(['method' => $method]);

            if ($data['mfaEnabled'] === true) {
                if ($method === 'totp') {
                    $secret = $data['mfaSecret'] ?? null;
                    $code = $data['mfaCode'] ?? null;
                    if (! $secret || ! $code || ! $this->isValidTotpCode($secret, $code)) {
                        throw ValidationException::withMessages([
                            'mfaCode' => ['Código TOTP inválido para a chave informada.'],
                        ]);
                    }
                    $mfaRecord->totp_secret = Crypt::encryptString($secret);
                    $mfaRecord->credential_id = null;
                }

                if ($method === 'certificate') {
                    $credentialId = $data['credentialId'] ?? null;
                    if (! $credentialId) {
                        throw ValidationException::withMessages([
                            'credentialId' => ['Credencial de certificado é obrigatória para ativar este método.'],
                        ]);
                    }
                    $mfaRecord->credential_id = $credentialId;
                    $mfaRecord->totp_secret = null;
                }
            }

            $mfaRecord->enabled = $data['mfaEnabled'];
            $mfaRecord->verified_at = $data['mfaEnabled'] ? now() : null;
            $mfaRecord->save();
        }
        if (array_key_exists('securityEmailAlerts', $data)) {
            $preferences->security_email_alerts = $data['securityEmailAlerts'];
        }

        $preferences->save();

        $this->auditTrail->record($request, 'account.security_updated', $user, metadata: [
            'changed' => array_values(array_filter([
                $hasPasswordChange ? 'password' : null,
                array_key_exists('mfaEnabled', $data) ? 'mfaEnabled' : null,
                array_key_exists('securityEmailAlerts', $data) ? 'securityEmailAlerts' : null,
            ])),
        ]);

        return response()->json([
            'user' => SessionUserResource::make($user->refresh()->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog', 'mfaMethods'])),
        ]);
    }

    public function verifyMfa(VerifyMfaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $method = $data['method'];

        $mfaRecord = $user->mfaMethods()
            ->where('method', $method)
            ->where('enabled', true)
            ->first();

        if (! $mfaRecord) {
            throw ValidationException::withMessages([
                'method' => ['Método de MFA não está habilitado para este usuário.'],
            ]);
        }

        if ($method === 'totp') {
            $secret = $mfaRecord->totp_secret ? Crypt::decryptString($mfaRecord->totp_secret) : null;
            if (! $secret || ! $this->isValidTotpCode($secret, (string) ($data['mfaCode'] ?? ''))) {
                throw ValidationException::withMessages([
                    'mfaCode' => ['Código TOTP inválido.'],
                ]);
            }
        }

        if ($method === 'certificate') {
            if (! $mfaRecord->credential_id || $mfaRecord->credential_id !== ($data['credentialId'] ?? null)) {
                throw ValidationException::withMessages([
                    'credentialId' => ['Credencial de certificado inválida.'],
                ]);
            }
        }

        $mfaRecord->last_used_at = now();
        $mfaRecord->save();

        $this->auditTrail->record($request, 'auth.mfa_verified', $user, metadata: [
            'method' => $method,
        ]);

        return response()->json(['verified' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auditTrail->record($request, 'auth.logout', $request->user());

        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sessão encerrada.',
        ]);
    }

    private function tokenResponse(User $user, Request $request, string $event, int $status = 200): JsonResponse
    {
        $newToken = $user->createToken('frontend:'.Str::uuid());
        $this->auditTrail->attachSessionData($newToken->accessToken, $request);
        $this->auditTrail->record($request, $event, $user, token: $newToken->accessToken);

        return response()->json([
            'user' => SessionUserResource::make($user->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog', 'mfaMethods'])),
            'token' => $newToken->plainTextToken,
        ], $status);
    }

    private function labelForAuditEvent(string $event): string
    {
        return match ($event) {
            'auth.login' => 'Login realizado',
            'auth.register' => 'Cadastro realizado',
            'auth.logout' => 'Logout realizado',
            'auth.mfa_verified' => 'MFA validado',
            'auth.session_revoked' => 'Sessão encerrada',
            'auth.other_sessions_revoked' => 'Outras sessões encerradas',
            'account.updated' => 'Conta atualizada',
            'account.security_updated' => 'Segurança da conta atualizada',
            'account.avatar_uploaded' => 'Foto de perfil atualizada',
            default => 'Atividade registrada',
        };
    }

    private function initialsFor(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));
        $initials = collect($parts)
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        return $initials ?: 'RD';
    }

    private function isValidTotpCode(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = intdiv(time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->generateTotpCode($secret, $timeSlice + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateTotpCode(string $secret, int $timeSlice): string
    {
        $key = $this->decodeBase32($secret);
        if ($key === '') {
            return '000000';
        }

        $binaryTime = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');

        $bits = '';
        foreach (str_split($clean) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                return '';
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }
}
