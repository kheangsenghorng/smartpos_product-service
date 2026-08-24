<?php

return [
    'secret' => env('JWT_SECRET', 'smartpos_jwt_super_secure_shared_secret_2026_key'),
    'algo' => env('JWT_ALGO', 'HS256'),
    'public_key' => env('JWT_PUBLIC_KEY', null),
    'leeway' => (int) env('JWT_LEEWAY', 60),
    'issuer' => env('JWT_ISSUER', 'smartpos-auth-service'),
    'verify_issuer' => env('JWT_VERIFY_ISSUER', false),
    'audience' => env('JWT_AUDIENCE', null),
    'verify_audience' => env('JWT_VERIFY_AUDIENCE', false),
    'identity_service_url' => env('IDENTITY_SERVICE_URL', 'http://identity-service:8000'),
];
