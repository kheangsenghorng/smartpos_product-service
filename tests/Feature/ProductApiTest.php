<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    public function test_can_create_product_with_barcode_option(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/products', [
            'name' => 'Coca-Cola 330ml',
            'sku' => 'COKE-330',
            'unit_id' => $unit->id,
            'track_inventory' => true,
            'is_active' => true,
            'code_option' => 'barcode',
            'selling_price' => 0.75,
            'cost_price' => 0.45,
            'currency_code' => 'USD',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Coca-Cola 330ml')
            ->assertJsonPath('data.sku', 'COKE-330');

        $productId = $response->json('data.id');

        // Check barcode created in product_codes
        $this->assertDatabaseHas('product_codes', [
            'business_uuid' => $businessUuid,
            'product_id' => $productId,
            'code_type' => 'barcode',
            'code_value' => 'COKE-330',
        ]);

        // Check price created in product_prices
        $this->assertDatabaseHas('product_prices', [
            'business_uuid' => $businessUuid,
            'product_id' => $productId,
            'selling_price' => 0.75,
            'cost_price' => 0.45,
        ]);
    }

    public function test_can_create_product_with_both_codes(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Can',
            'code' => 'CAN',
            'symbol' => 'can',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/products', [
            'name' => 'Energy Drink',
            'sku' => 'ENERGY-001',
            'unit_id' => $unit->id,
            'code_option' => 'both',
            'selling_price' => 1.50,
        ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');

        $this->assertDatabaseHas('product_codes', [
            'product_id' => $productId,
            'code_type' => 'barcode',
        ]);

        $this->assertDatabaseHas('product_codes', [
            'product_id' => $productId,
            'code_type' => 'qrcode',
        ]);
    }

    public function test_can_create_product_with_none_code_option(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Hour',
            'code' => 'HR',
            'symbol' => 'hr',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/products', [
            'name' => 'Consulting Service',
            'sku' => 'SRV-001',
            'unit_id' => $unit->id,
            'code_option' => 'none',
        ]);

        $response->assertStatus(201);
        $productId = $response->json('data.id');

        $this->assertDatabaseMissing('product_codes', [
            'product_id' => $productId,
        ]);
    }

    public function test_product_availability_dates_filter(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        // Available active product
        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Available Item',
            'sku' => 'AVAIL-01',
            'available_from' => Carbon::now()->subDays(5)->toDateString(),
            'available_until' => Carbon::now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        // Expired product
        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Expired Item',
            'sku' => 'EXP-01',
            'available_from' => Carbon::now()->subDays(10)->toDateString(),
            'available_until' => Carbon::now()->subDays(1)->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)->getJson('/api/v1/products?available_only=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'AVAIL-01');
    }

    public function test_tenant_isolation_enforced(): void
    {
        $businessA = (string) Str::uuid();
        $businessB = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessA,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        $productA = Product::create([
            'business_uuid' => $businessA,
            'unit_id' => $unit->id,
            'name' => 'Tenant A Item',
            'sku' => 'TA-001',
        ]);

        // Tenant B tries to access Tenant A's product
        $response = $this->actingAsJwt($businessB)->getJson("/api/v1/products/{$productA->id}");
        $response->assertStatus(403);
    }
}
