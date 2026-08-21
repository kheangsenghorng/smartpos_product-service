<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    /**
     * Generate a test JWT token for authentication in tests.
     */
    protected function generateTestJwt(
        string $businessUuid,
        ?string $userUuid = null,
        array $roles = ['owner'],
        array $permissions = ['*']
    ): string {
        $userUuid = $userUuid ?: (string) Str::uuid();
        $payload = [
            'iss' => 'smartpos_identity',
            'sub' => $userUuid,
            'user_uuid' => $userUuid,
            'business_uuid' => $businessUuid,
            'tenant_id' => $businessUuid,
            'roles' => $roles,
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        return JWT::encode($payload, config('jwt.secret'), config('jwt.algo', 'HS256'));
    }

    /**
     * Set JWT Authorization header on the test request.
     */
    protected function actingAsJwt(
        string $businessUuid,
        ?string $userUuid = null,
        array $roles = ['owner'],
        array $permissions = ['*']
    ): static {
        $token = $this->generateTestJwt($businessUuid, $userUuid, $roles, $permissions);
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }
}
