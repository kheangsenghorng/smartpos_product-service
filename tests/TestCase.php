<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected static ?string $testPrivateKey = null;
    protected static ?string $testPublicKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$testPrivateKey === null) {
            $res = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            $priv = '';
            openssl_pkey_export($res, $priv);
            static::$testPrivateKey = $priv;
            $details = openssl_pkey_get_details($res);
            static::$testPublicKey = $details['key'];
        }

        config(['jwt.public_key' => static::$testPublicKey]);
    }

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

        return JWT::encode($payload, static::$testPrivateKey, 'RS256');
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
