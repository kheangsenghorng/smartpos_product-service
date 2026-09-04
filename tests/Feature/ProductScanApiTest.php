<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductScanApiTest extends TestCase
{
    public function test_can_scan_product_by_barcode(): void
    {
        $businessUuid = (string) Str::uuid();

        $category = Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Beverages',
            'code' => 'BEV',
        ]);

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Can',
            'symbol' => 'can',
            'precision' => 0,
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Iced Green Tea',
            'sku' => 'TEA-ICED-01',
            'is_active' => true,
        ]);

        ProductPrice::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'selling_price' => 2.50,
            'cost_price' => 1.20,
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        ProductCode::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'code_type' => 'barcode',
            'symbology' => 'EAN13',
            'code_value' => '8850001234567',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/products/scan/8850001234567');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_name', 'Iced Green Tea')
            ->assertJsonPath('data.sku', 'TEA-ICED-01')
            ->assertJsonPath('data.scanned_code', '8850001234567')
            ->assertJsonPath('data.price.selling_price', 2.5)
            ->assertJsonPath('data.category.name', 'Beverages')
            ->assertJsonPath('data.unit.symbol', 'can');
    }

    public function test_can_scan_variant_by_sku(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'symbol' => 'pcs',
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Cotton T-Shirt',
            'sku' => 'TSHIRT-BASE',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'name' => 'Size L / Blue',
            'sku' => 'TSHIRT-L-BLU',
            'is_active' => true,
        ]);

        ProductPrice::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'selling_price' => 19.99,
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/products/scan/TSHIRT-L-BLU');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_name', 'Cotton T-Shirt')
            ->assertJsonPath('data.variant_name', 'Size L / Blue')
            ->assertJsonPath('data.sku', 'TSHIRT-L-BLU')
            ->assertJsonPath('data.price.selling_price', 19.99);
    }

    public function test_scan_returns_404_for_non_existent_code(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/products/scan/UNKNOWN999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_scan_enforces_tenant_isolation(): void
    {
        $businessA = (string) Str::uuid();
        $businessB = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessA,
            'name' => 'Item',
            'symbol' => 'itm',
        ]);

        Product::create([
            'business_uuid' => $businessA,
            'unit_id' => $unit->id,
            'name' => 'Business A Secret Item',
            'sku' => 'SECRET-A-100',
            'is_active' => true,
        ]);

        // Business B attempts to scan Business A's SKU
        $response = $this->actingAsJwt($businessB)
            ->getJson('/api/v1/products/scan/SECRET-A-100');

        $response->assertStatus(404);
    }
}
