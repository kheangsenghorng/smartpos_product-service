<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization bearer token is missing.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $secret = config('jwt.secret');
            $algo = config('jwt.algo', 'HS256');

            if ($algo === 'RS256') {
                $publicKey = config('jwt.public_key');
                $key = new Key($publicKey, 'RS256');
            } else {
                $key = new Key($secret, $algo);
            }

            JWT::$leeway = config('jwt.leeway', 60);
            $decoded = JWT::decode($token, $key);
            $payload = (array) $decoded;

            $userUuid = $payload['sub'] ?? ($payload['user_uuid'] ?? null);
            $businessUuid = $payload['business_uuid'] ?? ($payload['tenant_id'] ?? null);
            $roles = (array) ($payload['roles'] ?? []);
            $permissions = (array) ($payload['permissions'] ?? []);

            $request->attributes->set('auth_user_uuid', $userUuid);
            $request->attributes->set('auth_business_uuid', $businessUuid);
            $request->attributes->set('auth_roles', $roles);
            $request->attributes->set('auth_permissions', $permissions);
            $request->attributes->set('jwt_payload', $payload);

            // Populate request header/query helper if needed
            if ($businessUuid && !$request->has('business_uuid')) {
                $request->merge(['business_uuid' => $businessUuid]);
            }

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired authentication token: ' . $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
