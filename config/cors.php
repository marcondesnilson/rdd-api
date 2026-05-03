<?php

$defaultFrontendOrigins = [
    'http://localhost:8080',
    'http://localhost:8081',
    'https://republica-do-direito.lovable.app',
    'https://republica-do-direito.pages.dev',
    'https://republicadodireito.com.br',
    'https://www.republicadodireito.com.br',
];

$envFrontendOrigins = array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URLS', '')),
));

$frontendOrigins = array_values(array_unique(array_merge(
    $defaultFrontendOrigins,
    $envFrontendOrigins,
)));

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $frontendOrigins,

    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.lovableproject\.com$#',
        '#^https://[a-z0-9-]+\.lovable\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
