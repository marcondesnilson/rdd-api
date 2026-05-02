<?php

$frontendOrigins = array_filter(array_map(
    'trim',
    explode(',', env('FRONTEND_URLS', 'http://localhost:8080,http://localhost:8081')),
));

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $frontendOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
