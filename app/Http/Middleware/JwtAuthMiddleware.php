<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token = substr($header, 7);
        $payload = $this->verifyToken($token);

        if (! $payload) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $userUuid = $payload['user_uuid'] ?? $payload['sub'] ?? null;
        $businessUuid = $payload['business_uuid'] 
            ?? ($payload['tenant_id'] 
            ?? ($request->header('X-Business-Uuid') 
            ?? $request->input('business_uuid')));
        $permissions = (array) ($payload['permissions'] ?? []);

        // Flexible role extraction (supports array 'roles', string 'role', or nested)
        $rawRoles = $payload['roles'] ?? $payload['role'] ?? [];
        if (is_string($rawRoles)) {
            $rawRoles = [$rawRoles];
        }
        $roles = array_values(array_unique(array_filter(array_map('strtolower', (array) $rawRoles))));

        // Attach decoded claims to request attributes
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('user_uuid', $userUuid);
        $request->attributes->set('auth_user_uuid', $userUuid);
        $request->attributes->set('jwt_permissions', $permissions);
        $request->attributes->set('auth_permissions', $permissions);
        $request->attributes->set('jwt_roles', $roles);
        $request->attributes->set('auth_roles', $roles);
        $request->attributes->set('business_uuid', $businessUuid);
        $request->attributes->set('auth_business_uuid', $businessUuid);

        if ($businessUuid && ! $request->has('business_uuid')) {
            $request->merge(['business_uuid' => $businessUuid]);
        }

        return $next($request);
    }

    private function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        // Decode and validate header
        $headerJson = $this->base64UrlDecode($headerB64);
        if (! $headerJson) {
            return null;
        }

        $header = json_decode($headerJson, true);
        if (! is_array($header) || empty($header['alg'])) {
            return null;
        }

        $tokenAlgo = strtoupper((string) $header['alg']);
        if ($tokenAlgo === 'NONE') {
            return null;
        }

        $publicKey = config('jwt.public_key');
        if (is_string($publicKey)) {
            if (str_starts_with($publicKey, 'file://')) {
                $keyPath = substr($publicKey, 7);
                if (! str_starts_with($keyPath, '/') && function_exists('base_path')) {
                    $keyPath = base_path($keyPath);
                }
                $publicKey = file_exists($keyPath) ? file_get_contents($keyPath) : null;
            } elseif (file_exists($publicKey)) {
                $publicKey = file_get_contents($publicKey);
            }
        }

        $secret = config('jwt.secret');

        $isVerified = false;

        // Asymmetric Verification (RS256, RS384, RS512)
        if (in_array($tokenAlgo, ['RS256', 'RS384', 'RS512'], true) && ! empty($publicKey)) {
            $algoMap = [
                'RS256' => OPENSSL_ALGO_SHA256,
                'RS384' => OPENSSL_ALGO_SHA384,
                'RS512' => OPENSSL_ALGO_SHA512,
            ];
            $openSslAlgo = $algoMap[$tokenAlgo] ?? OPENSSL_ALGO_SHA256;
            $rawSig = $this->base64UrlDecode($sigB64);
            if ($rawSig !== null && $rawSig !== false) {
                $isVerified = @openssl_verify("$headerB64.$payloadB64", $rawSig, $publicKey, $openSslAlgo) === 1;
            }
        } elseif ($tokenAlgo === 'HS256' && ! empty($secret)) {
            // Symmetric HMAC Verification
            $expectedSig = $this->base64UrlEncode(
                hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
            );
            $isVerified = hash_equals($expectedSig, $sigB64);
        }

        if (! $isVerified) {
            return null;
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($payloadB64);

        if (! $payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson, true);

        if (! is_array($payload)) {
            return null;
        }

        // Validate expiration (exp)
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Validate Not Before (nbf)
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }

        // Validate issuer (iss) if configured
        if (config('jwt.verify_issuer', false)) {
            $expectedIssuer = config('jwt.issuer');
            if ($expectedIssuer && ! $this->isIssuerValid($payload['iss'] ?? null, $expectedIssuer)) {
                return null;
            }
        }

        // Validate audience (aud) if configured
        $expectedAudience = config('jwt.audience');
        if ($expectedAudience) {
            $tokenAud = $payload['aud'] ?? null;
            if (is_array($tokenAud)) {
                if (! in_array($expectedAudience, $tokenAud, true)) {
                    return null;
                }
            } elseif ($tokenAud !== $expectedAudience) {
                return null;
            }
        }

        return $payload;
    }

    private function isIssuerValid(?string $tokenIssuer, string $expectedIssuer): bool
    {
        if ($tokenIssuer === $expectedIssuer) {
            return true;
        }

        $tokenHost = parse_url($tokenIssuer ?? '', PHP_URL_HOST);
        $expectedHost = parse_url($expectedIssuer, PHP_URL_HOST);

        if ($tokenHost && $expectedHost && $tokenHost === $expectedHost) {
            return true;
        }

        $internalHost = config('jwt.internal_issuer_host', 'identity-service');
        if ($tokenHost === $internalHost && in_array($expectedHost, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        return false;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
