<?php

use App\Auditing\PublicIpAddressResolver;
use OwenIt\Auditing\Models\Audit;
use OwenIt\Auditing\Resolvers\UrlResolver;
use OwenIt\Auditing\Resolvers\UserAgentResolver;
use OwenIt\Auditing\Resolvers\UserResolver;

$csv = static fn (?string $value): array => array_values(array_filter(
    array_map('trim', explode(',', (string) $value)),
));

return [
    'enabled' => env('AUDITING_ENABLED', true),

    'implementation' => Audit::class,

    'user' => [
        'morph_prefix' => 'user',
        'guards' => [
            'api',
            'web',
        ],
        'resolver' => UserResolver::class,
    ],

    'resolvers' => [
        'ip_address' => PublicIpAddressResolver::class,
        'user_agent' => UserAgentResolver::class,
        'url' => UrlResolver::class,
    ],

    'events' => [
        'created',
        'updated',
        'deleted',
        'restored',
    ],

    'strict' => false,

    'exclude' => [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'token',
        'secret',
        'client_secret',
        'credentials',
        'credential',
    ],

    'empty_values' => true,
    'allowed_empty_values' => [
        'retrieved',
    ],
    'allowed_array_values' => false,
    'timestamps' => false,
    'threshold' => 0,
    'driver' => 'database',

    'drivers' => [
        'database' => [
            'table' => 'audits',
            'connection' => null,
        ],
    ],

    'ip' => [
        'trusted_proxies' => $csv(env('AUDIT_TRUSTED_PROXY_IPS')),
    ],

    'queue' => [
        'enable' => false,
        'connection' => 'sync',
        'queue' => 'default',
        'delay' => 0,
    ],

    'console' => false,
];
