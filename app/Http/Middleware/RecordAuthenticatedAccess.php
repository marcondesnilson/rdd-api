<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuthenticatedAccess
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user instanceof User) {
            $this->auditTrail->touchLastAccess($token, $request);
        }

        $response = $next($request);

        if ($user instanceof User) {
            $this->auditTrail->record(
                request: $request,
                event: 'api.access',
                actor: $user,
                target: $user,
                metadata: ['route' => $request->route()?->getName()],
                response: $response,
                token: $token,
            );
        }

        return $response;
    }
}
