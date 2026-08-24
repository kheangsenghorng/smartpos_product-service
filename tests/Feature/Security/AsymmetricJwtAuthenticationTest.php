<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AsymmetricJwtAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate a 2048-bit RSA key pair for testing
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKeyStr = '';
        openssl_pkey_export($res, $privateKeyStr);
        $this->privateKey = $privateKeyStr;
        $details = openssl_pkey_get_details($res);
        $this->publicKey = $details['key'];

        // Configure Product Service to use this public key ONLY (no private key)
        config(['jwt.public_key' => $this->publicKey]);
        config(['jwt.secret' => null]);
    }

    private function createRs256Token(array $payloadOverrides = [], ?string $signingKey = null): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'RS256'];
        $payload = array_merge([
            'iss' => 'smartpos-auth-service',
            'aud' => 'smartpos-api',
            'sub' => (string) Str::uuid(),
            'user_uuid' => (string) Str::uuid(),
            'business_uuid' => (string) Str::uuid(),
            'sid' => (string) Str::uuid(),
            'roles' => ['admin'],
            'permissions' => ['products.view', 'products.create'],
            'iat' => time(),
            'exp' => time() + 3600,
        ], $payloadOverrides);

        $headerB64 = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        $dataToSign = "$headerB64.$payloadB64";
        $signature = '';
        openssl_sign($dataToSign, $signature, $signingKey ?? $this->privateKey, OPENSSL_ALGO_SHA256);

        $sigB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "$headerB64.$payloadB64.$sigB64";
    }

    /**
     * 1. Valid RS256 token signed by Private Key is accepted by Product Service using Public Key.
     */
    public function test_valid_rs256_token_signed_by_private_key_is_accepted(): void
    {
        $token = $this->createRs256Token();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/products');

        $response->assertStatus(200);
    }

    /**
     * 2. Forged RS256 token signed by an unauthorized/fake private key is rejected (401).
     */
    public function test_forged_rs256_token_with_unauthorized_key_is_rejected(): void
    {
        // Generate an attacker's rogue RSA key pair
        $attackerRes = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $attackerPrivateKey = '';
        openssl_pkey_export($attackerRes, $attackerPrivateKey);

        $forgedToken = $this->createRs256Token(['roles' => ['admin']], $attackerPrivateKey);

        $response = $this->withHeader('Authorization', "Bearer $forgedToken")
            ->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    /**
     * 3. Tampered payload is rejected (401).
     */
    public function test_tampered_payload_in_rs256_token_is_rejected(): void
    {
        $validToken = $this->createRs256Token();
        $parts = explode('.', $validToken);

        // Attacker alters payload to change business_uuid / permissions
        $tamperedPayload = json_encode(['roles' => ['admin'], 'business_uuid' => (string) Str::uuid()]);
        $tamperedPayloadB64 = rtrim(strtr(base64_encode($tamperedPayload), '+/', '-_'), '=');

        $tamperedToken = "{$parts[0]}.{$tamperedPayloadB64}.{$parts[2]}";

        $response = $this->withHeader('Authorization', "Bearer $tamperedToken")
            ->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    /**
     * 4. Algorithm downgrade attack (alg: none) is rejected (401).
     */
    public function test_algorithm_none_downgrade_is_rejected(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'none'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['iss' => 'smartpos-auth-service', 'exp' => time() + 3600])), '+/', '-_'), '=');
        $token = "$header.$payload.";

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    /**
     * 5. Expired RS256 token is rejected (401).
     */
    public function test_expired_rs256_token_is_rejected(): void
    {
        $expiredToken = $this->createRs256Token(['exp' => time() - 100]);

        $response = $this->withHeader('Authorization', "Bearer $expiredToken")
            ->getJson('/api/v1/products');

        $response->assertStatus(401);
    }
}
