<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCodeAndVariantTest extends TestCase
{
    public function test_can_create_variant_and_generate_codes(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'T-Shirt',
            'sku' => 'TSHIRT',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson("/api/v1/products/{$product->id}/variants", [
            'name' => 'Size Large',
            'sku' => 'TSHIRT-L',
            'code_option' => 'barcode',
            'selling_price' => 19.99,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'TSHIRT-L');

        $variantId = $response->json('data.id');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'sku' => 'TSHIRT-L',
        ]);

        $this->assertDatabaseHas('product_codes', [
            'product_variant_id' => $variantId,
            'code_type' => 'barcode',
            'code_value' => 'TSHIRT-L',
        ]);

        $this->assertDatabaseHas('product_prices', [
            'product_variant_id' => $variantId,
            'selling_price' => 19.99,
        ]);
    }

    public function test_can_manually_generate_product_code(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Notebook',
            'sku' => 'NOTE-001',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson("/api/v1/products/{$product->id}/codes/generate", [
            'code_option' => 'qrcode',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('product_codes', [
            'product_id' => $product->id,
            'code_type' => 'qrcode',
        ]);
    }
}
