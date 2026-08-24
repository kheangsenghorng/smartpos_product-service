<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHeadersAndThrottlingTest extends TestCase
{
    public function test_api_responses_include_hardening_security_headers(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_unauthenticated_requests_are_rejected_with_json(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_blocks_known_malicious_scanner_user_agents(): void
    {
        $blockedScanners = ['sqlmap/1.5.2', 'nikto/2.1.6', 'gobuster/3.1.0', 'masscan/1.0', 'dirbuster'];

        foreach ($blockedScanners as $scanner) {
            $response = $this->withHeaders([
                'User-Agent' => $scanner,
            ])->getJson('/api/health');

            $response->assertStatus(403);
            $response->assertJson([
                'success' => false,
                'message' => 'Access denied.',
            ]);
        }
    }

    public function test_allows_legitimate_client_user_agents(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'SmartPOS-Client/1.0 (Mobile POS)',
        ])->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
        ]);
    }
}
