<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\User;
use App\Models\UserAccessLog;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit as AuditModel;

class AdminUserController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $users = User::query()
            ->with(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog'])
            ->latest()
            ->get();

        return response()->json([
            'users' => SessionUserResource::collection($users),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        return response()->json([
            'user' => SessionUserResource::make($user->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog'])),
        ]);
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'initials' => $this->initialsFor($data['name']),
            'headline' => $data['headline'] ?? 'Membro da República',
        ]);
        $user->preferences()->create();
        $user->roleRecord()->create(['role' => $data['role']]);
        $user->verification()->create([
            'status' => in_array($data['role'], ['admin', 'editor'], true) ? 'approved' : 'none',
        ]);

        $this->auditTrail->record($request, 'admin.user_created', $request->user(), $user, [
            'role' => $data['role'],
        ]);

        return response()->json([
            'user' => SessionUserResource::make($user->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog'])),
        ], 201);
    }

    public function update(UpdateAdminUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($value): bool => $value !== null));
        $user->save();

        if (array_key_exists('phone', $data)) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['phone' => $data['phone']],
            );
        }

        if (array_key_exists('role', $data)) {
            $user->roleRecord()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $data['role']],
            );
        }

        if (array_key_exists('status', $data)) {
            $verificationStatus = match ($data['status']) {
                'pendente' => 'pending',
                'suspenso' => 'rejected',
                default => 'approved',
            };

            $user->verification()->updateOrCreate(
                ['user_id' => $user->id],
                ['status' => $verificationStatus],
            );
        }

        $this->auditTrail->record($request, 'admin.user_updated', $request->user(), $user, [
            'updated' => array_keys($data),
        ]);

        return response()->json([
            'user' => SessionUserResource::make($user->fresh()->load(['profile', 'preferences', 'roleRecord', 'verification', 'latestLoginLog'])),
        ]);
    }

    public function sessions(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $sessions = $user->tokens()
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
                'current' => $token->id === $request->user()?->currentAccessToken()?->id,
            ])
            ->values();

        return response()->json(['sessions' => $sessions]);
    }

    public function logs(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $logs = UserAccessLog::query()
            ->with('actor:id,name,email')
            ->where('target_user_id', $user->id)
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (UserAccessLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
                'method' => $log->method,
                'path' => $log->path,
                'statusCode' => $log->status_code,
                'ip' => $log->ip_address,
                'userAgent' => $log->user_agent,
                'metadata' => $log->metadata,
                'occurredAt' => $log->occurred_at?->toISOString(),
            ])
            ->values();

        return response()->json(['logs' => $logs]);
    }

    public function audits(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $user->loadMissing(['profile', 'preferences', 'roleRecord', 'verification']);

        $auditableKeys = collect([
            [User::class, $user->id],
            [$user->profile?->getMorphClass(), $user->profile?->getKey()],
            [$user->preferences?->getMorphClass(), $user->preferences?->getKey()],
            [$user->roleRecord?->getMorphClass(), $user->roleRecord?->getKey()],
            [$user->verification?->getMorphClass(), $user->verification?->getKey()],
        ])->filter(fn (array $auditable): bool => filled($auditable[0]) && filled($auditable[1]));

        $audits = AuditModel::query()
            ->where(function ($query) use ($auditableKeys): void {
                foreach ($auditableKeys as [$type, $id]) {
                    $query->orWhere(function ($query) use ($type, $id): void {
                        $query
                            ->where('auditable_type', $type)
                            ->where('auditable_id', (string) $id);
                    });
                }
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AuditModel $audit): array => [
                'id' => (string) $audit->id,
                'event' => $audit->event,
                'auditableType' => class_basename($audit->auditable_type),
                'auditableId' => (string) $audit->auditable_id,
                'actor' => $audit->user ? [
                    'id' => (string) $audit->user->getKey(),
                    'name' => $audit->user->name,
                    'email' => $audit->user->email,
                ] : null,
                'oldValues' => $audit->old_values,
                'newValues' => $audit->new_values,
                'ip' => $audit->ip_address,
                'userAgent' => $audit->user_agent,
                'url' => $audit->url,
                'createdAt' => $audit->created_at?->toISOString(),
            ])
            ->values();

        return response()->json(['audits' => $audits]);
    }

    private function authorizeAdminAccess(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
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
