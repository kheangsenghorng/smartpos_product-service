<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScoutSearchTest extends TestCase
{
    public function test_product_model_implements_searchable_and_generates_searchable_array(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Cup',
            'code' => 'CUP',
            'symbol' => 'c',
        ]);

        $category = Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Hot Coffee',
            'code' => 'COF',
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Caramel Macchiato',
            'sku' => 'CM-001',
            'description' => 'Espresso with vanilla syrup and caramel drizzle',
            'is_active' => true,
        ]);

        $this->assertTrue(method_exists($product, 'toSearchableArray'));
        $this->assertTrue($product->shouldBeSearchable());

        $searchableArray = $product->toSearchableArray();

        $this->assertEquals($product->id, $searchableArray['id']);
        $this->assertEquals('Caramel Macchiato', $searchableArray['name']);
        $this->assertEquals('CM-001', $searchableArray['sku']);
        $this->assertEquals($businessUuid, $searchableArray['business_uuid']);
        $this->assertTrue($searchableArray['is_active']);
    }

    public function test_category_and_brand_and_unit_implement_searchable(): void
    {
        $businessUuid = (string) Str::uuid();

        $category = Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Specialty Tea',
            'code' => 'TEA',
            'description' => 'Organic herbal teas',
        ]);

        $brand = Brand::create([
            'business_uuid' => $businessUuid,
            'name' => 'Twinings',
            'code' => 'TWIN',
            'description' => 'British tea brand',
        ]);

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Cup',
            'code' => 'CUP',
            'symbol' => 'c',
        ]);

        $catArray = $category->toSearchableArray();
        $this->assertEquals('Specialty Tea', $catArray['name']);
        $this->assertEquals('TEA', $catArray['code']);

        $brandArray = $brand->toSearchableArray();
        $this->assertEquals('Twinings', $brandArray['name']);
        $this->assertEquals('TWIN', $brandArray['code']);

        $unitArray = $unit->toSearchableArray();
        $this->assertEquals('Cup', $unitArray['name']);
        $this->assertEquals('CUP', $unitArray['code']);
    }

    public function test_api_search_filters_products_correctly(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'code' => 'PC',
            'symbol' => 'pc',
        ]);

        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Iced Americano',
            'sku' => 'AMER-01',
            'is_active' => true,
        ]);

        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Croissant',
            'sku' => 'CR-01',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/products?search=Americano');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals('Iced Americano', $items[0]['name']);
    }

    public function test_api_search_filters_categories_correctly(): void
    {
        $businessUuid = (string) Str::uuid();

        Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Cold Brew Beverages',
            'code' => 'CB-01',
            'is_active' => true,
        ]);

        Category::create([
            'business_uuid' => $businessUuid,
            'name' => 'Pastries',
            'code' => 'PAS-01',
            'is_active' => true,
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/categories?search=Brew');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals('Cold Brew Beverages', $items[0]['name']);
    }
}
