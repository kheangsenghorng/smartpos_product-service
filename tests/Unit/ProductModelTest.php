<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    public function test_product_availability_logic(): void
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
            'name' => 'Seasonal Gift Box',
            'sku' => 'GIFT-2026',
            'available_from' => '2026-11-01',
            'available_until' => '2026-12-31',
            'is_active' => true,
        ]);

        // Auto slug generation test
        $this->assertEquals('seasonal-gift-box', $product->slug);
        $this->assertNotEmpty($product->uuid);

        // Date before availability
        $this->assertFalse($product->isAvailable(Carbon::parse('2026-10-31')));

        // Dates during availability
        $this->assertTrue($product->isAvailable(Carbon::parse('2026-11-01')));
        $this->assertTrue($product->isAvailable(Carbon::parse('2026-12-15')));
        $this->assertTrue($product->isAvailable(Carbon::parse('2026-12-31')));

        // Date after availability
        $this->assertFalse($product->isAvailable(Carbon::parse('2027-01-01')));

        // Inactive product should be unavailable regardless of dates
        $product->is_active = false;
        $product->save();
        $this->assertFalse($product->isAvailable(Carbon::parse('2026-12-15')));
    }
}
