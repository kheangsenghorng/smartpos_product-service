<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductTrashAndRestoreTest extends TestCase
{
    public function test_can_view_trash_restore_and_force_delete_product(): void
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
            'name' => 'Deletable Item',
            'sku' => 'DEL-001',
            'is_active' => true,
        ]);

        // Soft delete the product
        $product->delete();
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // 1. List trash
        $trashResponse = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/products/trash');

        $trashResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sku', 'DEL-001');

        // 2. Restore product
        $restoreResponse = $this->actingAsJwt($businessUuid)
            ->postJson("/api/v1/products/{$product->id}/restore");

        $restoreResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id);

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);

        // Soft delete again and force delete
        $product->delete();

        // 3. Force delete
        $forceResponse = $this->actingAsJwt($businessUuid)
            ->deleteJson("/api/v1/products/{$product->id}/force");

        $forceResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_can_view_trash_restore_and_force_delete_category(): void
    {
        $businessUuid = (string) Str::uuid();

        $category = Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Temporary Category',
            'code' => 'TEMP-CAT',
            'is_active' => true,
        ]);

        $category->delete();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        // List trash
        $trashResponse = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/categories/trash');

        $trashResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'TEMP-CAT');

        // Restore
        $restoreResponse = $this->actingAsJwt($businessUuid)
            ->postJson("/api/v1/categories/{$category->id}/restore");

        $restoreResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);

        // Delete & Force Delete
        $category->delete();

        $forceResponse = $this->actingAsJwt($businessUuid)
            ->deleteJson("/api/v1/categories/{$category->id}/force");

        $forceResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
