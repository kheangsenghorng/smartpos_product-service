<?php

return [
    'secret' => env('JWT_SECRET', null),
    'algo' => env('JWT_ALGO', 'RS256'),
    'public_key' => env('JWT_PUBLIC_KEY') ?: (env('JWT_PUBLIC_KEY_FILE') ?: (file_exists(storage_path('certs/jwt-public.pem')) ? 'file://' . storage_path('certs/jwt-public.pem') : (file_exists('/run/secrets/identity-jwt/public.pem') ? '/run/secrets/identity-jwt/public.pem' : null))),
    'leeway' => (int) env('JWT_LEEWAY', 60),
    'issuer' => env('JWT_ISSUER', 'smartpos-auth-service'),
    'verify_issuer' => env('JWT_VERIFY_ISSUER', false),
    'audience' => env('JWT_AUDIENCE', null),
    'verify_audience' => env('JWT_VERIFY_AUDIENCE', false),
    'identity_service_url' => env('IDENTITY_SERVICE_URL', 'http://identity-service:8000'),
];
