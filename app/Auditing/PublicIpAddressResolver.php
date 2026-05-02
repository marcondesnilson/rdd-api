<?php

namespace App\Auditing;

use App\Support\PublicIpAddress;
use Illuminate\Support\Facades\Request;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

class PublicIpAddressResolver implements Resolver
{
    public static function resolve(Auditable $auditable): ?string
    {
        return app(PublicIpAddress::class)->fromRequest(
            Request::instance(),
            config('audit.ip.trusted_proxies', []),
        );
    }
}
