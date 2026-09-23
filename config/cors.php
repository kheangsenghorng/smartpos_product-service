<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',
        'docs/*',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
        [
            env('FRONTEND_URL'),
            env('POS_CLIENT_URL'),
        ]
    )))),

    'allowed_origins_patterns' => array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))),
        [
            '#^https?://(api|admin|pos|app)\.smartpos\.test(:[0-9]+)?$#',
            '#^https?://.*\.servicefixit\.me(:[0-9]+)?$#',
            '#^https?://.*\.ngrok(-free)?\.app$#',
            '#^https?://.*\.ngrok\.io$#',
            '#^https?://localhost:(80|8000|8080|8001|8002|8003|3000|3001|5173)$#',
            '#^https?://127\.0\.0\.1:(80|8000|8080|8001|8002|8003|3000|3001|5173)$#',
            '#^https?://(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[0-1])\.\d+\.\d+)(:[0-9]+)?$#',
        ]
    )))),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['*'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
