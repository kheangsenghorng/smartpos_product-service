<?php

namespace Tests\Feature;

use App\Models\Category;
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
        $businessUuid = (string) Str::uuid();

        $category = Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Food',
            'code' => 'FOOD',
        ]);

        $updateResponse = $this->actingAsJwt($businessUuid)->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'Delicious Food',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Delicious Food');

        $deleteResponse = $this->actingAsJwt($businessUuid)->deleteJson("/api/v1/categories/{$category->id}");
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
