<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    public function test_can_create_category(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/categories', [
            'name' => 'Beverages',
            'code' => 'BEV',
            'description' => 'Cold and hot drinks',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Beverages')
            ->assertJsonPath('data.code', 'BEV')
            ->assertJsonPath('data.business_uuid', $businessUuid);

        $this->assertDatabaseHas('categories', [
            'business_uuid' => $businessUuid,
            'code' => 'BEV',
        ]);
    }

    public function test_can_create_category_with_webp_image(): void
    {
        $disk = config('filesystems.default', 'public');
        Storage::fake($disk);
        $businessUuid = (string) Str::uuid();

        $file = UploadedFile::fake()->image('category.png', 400, 400);

        $response = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Desserts',
            'code' => 'DESSERT',
            'image' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $category = Category::where('code', 'DESSERT')->first();
        $this->assertNotNull($category->image_path);
        $this->assertStringContainsString('.webp', $category->image_path);
        Storage::disk($disk)->assertExists($category->image_path);
    }

    public function test_can_create_category_with_image_path_file_upload(): void
    {
        $disk = config('filesystems.default', 'public');
        Storage::fake($disk);
        $businessUuid = (string) Str::uuid();

        $file = UploadedFile::fake()->image('bakery.jpg', 300, 300);

        $response = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Bakery',
            'code' => 'BAKERY',
            'image_path' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $category = Category::where('code', 'BAKERY')->first();
        $this->assertNotNull($category->image_path);
        $this->assertStringContainsString('.webp', $category->image_path);
        Storage::disk($disk)->assertExists($category->image_path);
    }

    public function test_can_create_category_with_string_image_path(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/categories', [
            'name' => 'Snacks',
            'code' => 'SNACK',
            'image_path' => 'categories/pre_existing.webp',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.image_path', 'categories/pre_existing.webp');
    }

    public function test_category_parent_id_normalization(): void
    {
        $businessUuid = (string) Str::uuid();

        // 1. parent_id as empty string ""
        $response1 = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Category Empty Parent',
            'code' => 'CAT_EMPTY',
            'parent_id' => '',
        ]);
        $response1->assertStatus(201)
            ->assertJsonPath('data.parent_id', null);

        // 2. parent_id as "null" string
        $response2 = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Category Null String Parent',
            'code' => 'CAT_NULL_STR',
            'parent_id' => 'null',
        ]);
        $response2->assertStatus(201)
            ->assertJsonPath('data.parent_id', null);

        // 3. parent_id as "0" string
        $response3 = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Category Zero Parent',
            'code' => 'CAT_ZERO',
            'parent_id' => '0',
        ]);
        $response3->assertStatus(201)
            ->assertJsonPath('data.parent_id', null);

        // 4. parent_id as numeric string with valid parent ID
        $parentCategory = $response1->json('data.id');
        $response4 = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Sub Category',
            'code' => 'SUB_CAT',
            'parent_id' => (string) $parentCategory,
        ]);
        $response4->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parentCategory);
    }

    public function test_category_code_must_be_unique_per_business(): void
    {
        $businessUuid = (string) Str::uuid();

        Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Drinks',
            'code' => 'DRK',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/categories', [
            'name' => 'Other Drinks',
            'code' => 'DRK',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_list_hierarchical_category_tree(): void
    {
        $businessUuid = (string) Str::uuid();

        $parent = Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Drinks',
            'code' => 'DRK',
        ]);

        $child = Category::create([
            'business_uuid' => $businessUuid,
            'parent_id' => $parent->id,
            'name' => 'Coffee',
            'code' => 'COF',
        ]);

        $response = $this->actingAsJwt($businessUuid)->getJson('/api/v1/categories?tree=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.children.0.name', 'Coffee');
    }

    public function test_can_update_and_delete_category(): void
    {
        $disk = config('filesystems.default', 'public');
        Storage::fake($disk);
        $businessUuid = (string) Str::uuid();

        $file = UploadedFile::fake()->image('food.png', 200, 200);

        $createResponse = $this->actingAsJwt($businessUuid)->post('/api/v1/categories', [
            'name' => 'Food',
            'code' => 'FOOD',
            'image' => $file,
        ]);
        $createResponse->assertStatus(201);
        $categoryId = $createResponse->json('data.id');
        $oldImagePath = $createResponse->json('data.image_path');

        Storage::disk($disk)->assertExists($oldImagePath);

        // Update with new image
        $newFile = UploadedFile::fake()->image('updated_food.jpg', 200, 200);
        $updateResponse = $this->actingAsJwt($businessUuid)->post("/api/v1/categories/{$categoryId}", [
            '_method' => 'PUT',
            'name' => 'Delicious Food',
            'image' => $newFile,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Delicious Food');
        $newImagePath = $updateResponse->json('data.image_path');

        $this->assertNotEquals($oldImagePath, $newImagePath);
        Storage::disk($disk)->assertExists($newImagePath);
        Storage::disk($disk)->assertMissing($oldImagePath);

        $deleteResponse = $this->actingAsJwt($businessUuid)->deleteJson("/api/v1/categories/{$categoryId}");
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
        Storage::disk($disk)->assertMissing($newImagePath);
    }
}
