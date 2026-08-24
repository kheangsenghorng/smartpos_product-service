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
}
