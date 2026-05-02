<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccessLog;
use App\Support\PublicIpAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuditTrail
{
    public function __construct(private readonly PublicIpAddress $publicIpAddress) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Request $request,
        string $event,
        ?User $actor = null,
        ?User $target = null,
        array $metadata = [],
        ?SymfonyResponse $response = null,
        ?Model $token = null,
    ): UserAccessLog {
        $token ??= $actor?->currentAccessToken();

        return UserAccessLog::query()->create([
            'actor_user_id' => $actor?->id,
            'target_user_id' => $target?->id ?? $actor?->id,
            'personal_access_token_id' => $token?->getKey(),
            'event' => $event,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status_code' => $response?->getStatusCode(),
            'ip_address' => $this->ipAddress($request),
            'user_agent' => $this->userAgent($request),
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function attachSessionData(Model $token, Request $request): void
    {
        $token->forceFill([
            'ip_address' => $this->ipAddress($request),
            'user_agent' => $this->userAgent($request),
            'last_used_ip_address' => $this->ipAddress($request),
        ])->save();
    }

    public function touchLastAccess(?Model $token, Request $request): void
    {
        if (! $token) {
            return;
        }

        $token->forceFill([
            'last_used_ip_address' => $this->ipAddress($request),
        ])->save();
    }

    public function browserFromAgent(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Sessão API';
        }

        return match (true) {
            Str::contains($userAgent, 'Edg/') => 'Microsoft Edge',
            Str::contains($userAgent, 'Chrome/') => 'Chrome',
            Str::contains($userAgent, 'Firefox/') => 'Firefox',
            Str::contains($userAgent, 'Safari/') => 'Safari',
            default => 'Sessão API',
        };
    }

    private function userAgent(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        return $userAgent ? Str::limit($userAgent, 1000, '') : null;
    }

    private function ipAddress(Request $request): ?string
    {
        return $this->publicIpAddress->fromRequest($request, config('audit.ip.trusted_proxies', []));
    }
}
