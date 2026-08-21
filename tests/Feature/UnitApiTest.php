<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitApiTest extends TestCase
{
    public function test_can_create_unit(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/units', [
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
            'precision' => 0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'PCS');
    }

    public function test_cannot_delete_unit_if_assigned_to_products(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Bottle',
            'code' => 'BTL',
            'symbol' => 'btl',
        ]);

        Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Water Bottle',
            'sku' => 'WATER-001',
        ]);

        $response = $this->actingAsJwt($businessUuid)->deleteJson("/api/v1/units/{$unit->id}");
        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
