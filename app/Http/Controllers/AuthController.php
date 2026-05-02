<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\User;
use App\Models\UserAccessLog;
use App\Services\AuditTrail;
use App\Services\CdnFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'user' => SessionUserResource::make($user->refresh()->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog'])),
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
                'device' => $this->deviceNameFromToken($token->name),
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
                'detail' => $this->deviceNameFromToken($token->name),
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
            'user' => SessionUserResource::make($user->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog'])),
            'token' => $newToken->plainTextToken,
        ], $status);
    }

    private function labelForAuditEvent(string $event): string
    {
        return match ($event) {
            'auth.login' => 'Login realizado',
            'auth.register' => 'Cadastro realizado',
            'auth.logout' => 'Logout realizado',
            'auth.session_revoked' => 'Sessão encerrada',
            'auth.other_sessions_revoked' => 'Outras sessões encerradas',
            'account.updated' => 'Conta atualizada',
            'account.avatar_uploaded' => 'Foto de perfil atualizada',
            default => 'Atividade registrada',
        };
    }

    private function deviceNameFromToken(string $name): string
    {
        return Str::before($name, ':') ?: 'frontend';
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
}
