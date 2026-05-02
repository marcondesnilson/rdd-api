<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class PublicIpAddress
{
    /**
     * @param  list<string>  $trustedProxies
     */
    public function fromRequest(Request $request, array $trustedProxies = []): ?string
    {
        $remoteAddress = $request->server('REMOTE_ADDR');

        if (! $this->isTrustedProxy($remoteAddress, $trustedProxies)) {
            return $remoteAddress;
        }

        foreach (['CF-Connecting-IP', 'True-Client-IP'] as $header) {
            $ip = $this->publicIp($request->headers->get($header));

            if ($ip) {
                return $ip;
            }
        }

        foreach (explode(',', (string) $request->headers->get('X-Forwarded-For')) as $candidate) {
            $ip = $this->publicIp($candidate);

            if ($ip) {
                return $ip;
            }
        }

        return $remoteAddress;
    }

    /**
     * @param  list<string>  $trustedProxies
     */
    private function isTrustedProxy(?string $remoteAddress, array $trustedProxies): bool
    {
        if (! $remoteAddress || $trustedProxies === []) {
            return false;
        }

        return IpUtils::checkIp($remoteAddress, $trustedProxies);
    }

    private function publicIp(?string $candidate): ?string
    {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            return null;
        }

        return filter_var(
            $candidate,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) ?: null;
    }
}
