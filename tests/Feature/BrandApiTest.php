<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandApiTest extends TestCase
{
    public function test_can_create_brand(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/brands', [
            'name' => 'Coca Cola',
            'code' => 'COKE',
            'description' => 'Beverage company',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Coca Cola');

        $this->assertDatabaseHas('brands', [
            'business_uuid' => $businessUuid,
            'code' => 'COKE',
        ]);
    }

    public function test_brand_code_unique_per_business(): void
    {
        $businessUuid = (string) Str::uuid();

        Brand::create([
            'business_uuid' => $businessUuid,
            'name' => 'Pepsi',
            'code' => 'PEPSI',
        ]);

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/brands', [
            'name' => 'Pepsi Duplicate',
            'code' => 'PEPSI',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}
