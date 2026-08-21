<?php

return [
    'secret' => env('JWT_SECRET', 'smartpos_jwt_super_secure_shared_secret_2026_key'),
    'algo' => env('JWT_ALGO', 'HS256'),
    'public_key' => env('JWT_PUBLIC_KEY', null),
    'leeway' => (int) env('JWT_LEEWAY', 60),
];
