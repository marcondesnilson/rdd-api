<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Resources\SessionUserResource;
use App\Models\User;
use App\Models\UserAccessLog;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function sessions(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminAccess($request);

        $sessions = $user->tokens()
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

    private function authorizeAdminAccess(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'editor'], true), 403);
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

    private function deviceNameFromToken(string $name): string
    {
        return Str::before($name, ':') ?: 'frontend';
    }
}
