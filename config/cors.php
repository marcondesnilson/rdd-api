<?php

$frontendOrigins = array_filter(array_map(
    'trim',
    explode(',', env('FRONTEND_URLS', 'http://localhost:8080,http://localhost:8081,https://republica-do-direito.lovable.app,https://republica-do-direito.pages.dev')),
));

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
