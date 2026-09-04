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

    'allowed_origins' => array_values(array_filter([
        // SmartPOS Production API
        'https://smartpos-api.servicefixit.me',

        // Web Admin / Frontends
        env('FRONTEND_URL'),
        env('POS_CLIENT_URL'),

        // Local development origins
        'http://localhost',
        'http://localhost:80',
        'http://localhost:8000',
        'http://localhost:8080',
        'http://localhost:8003',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',
        'http://127.0.0.1',
        'http://127.0.0.1:80',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:8003',
        'http://api.smartpos.test',
        'https://api.smartpos.test',
    ])),

    'allowed_origins_patterns' => [
        '#^https?://(api|admin|pos|app)\.smartpos\.test(:[0-9]+)?$#',
        '#^https?://.*\.ngrok(-free)?\.app$#',
        '#^https?://.*\.ngrok\.io$#',
        '#^https?://localhost:(80|8000|8080|8001|8002|8003|3000|3001|5173)$#',
        '#^https?://127\.0\.0\.1:(80|8000|8080|8001|8002|8003|3000|3001|5173)$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-User-Uuid',
        'X-Device-Uuid',
    ],

    'exposed_headers' => [
        'Retry-After',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
