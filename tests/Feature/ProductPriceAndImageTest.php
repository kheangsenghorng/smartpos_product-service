<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductPriceAndImageTest extends TestCase
{
    public function test_can_manage_product_prices(): void
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
            'name' => 'Coffee Maker',
            'sku' => 'COFFEE-M01',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson("/api/v1/products/{$product->id}/prices", [
            'currency_code' => 'USD',
            'selling_price' => 89.99,
            'cost_price' => 50.00,
            'minimum_price' => 75.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.selling_price', '89.9900');

        $priceId = $response->json('data.id');

        $updateResponse = $this->actingAsJwt($businessUuid)->putJson("/api/v1/product-prices/{$priceId}", [
            'selling_price' => 85.00,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.selling_price', '85.0000');
    }

    public function test_can_manage_product_images(): void
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
            'name' => 'Desk Lamp',
            'sku' => 'LAMP-01',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson("/api/v1/products/{$product->id}/images", [
            'image_path' => 'products/lamp/main.jpg',
            'alt_text' => 'Desk Lamp Main View',
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.image_path', 'products/lamp/main.jpg');

        $imageId = $response->json('data.id');

        $deleteResponse = $this->actingAsJwt($businessUuid)->deleteJson("/api/v1/product-images/{$imageId}");
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('product_images', ['id' => $imageId]);
    }
}
