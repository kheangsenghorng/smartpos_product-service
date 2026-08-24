<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_list_all_brands_without_business_uuid(): void
    {
        $businessA = (string) Str::uuid();
        $businessB = (string) Str::uuid();

        Brand::create([
            'business_uuid' => $businessA,
            'name' => 'Brand A',
            'code' => 'BA',
        ]);

        Brand::create([
            'business_uuid' => $businessB,
            'name' => 'Brand B',
            'code' => 'BB',
        ]);

        // Global admin JWT token with no business_uuid
        $token = $this->generateTestJwt('', null, ['admin']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/brands');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_upload_and_convert_brand_logo_to_webp(): void
    {
        Storage::fake('public');
        $businessUuid = (string) Str::uuid();

        $file = UploadedFile::fake()->image('logo.png', 400, 400);

        $response = $this->actingAsJwt($businessUuid)->post('/api/v1/brands', [
            'name' => 'Nike',
            'code' => 'NIKE',
            'logo' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $brand = Brand::where('code', 'NIKE')->first();
        $this->assertNotNull($brand->logo_path);
        $this->assertStringContainsString('.webp', $brand->logo_path);
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
