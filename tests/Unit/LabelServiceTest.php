<?php

namespace Tests\Unit;

use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\Unit;
use App\Services\LabelService;
use App\Services\ProductCodeService;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelServiceTest extends TestCase
{
    public function test_label_preview_and_log_recording(): void
    {
        $businessUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Can',
            'code' => 'CAN',
            'symbol' => 'can',
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Green Tea 500ml',
            'sku' => 'TEA-500',
        ]);

        $template = LabelTemplate::create([
            'business_uuid' => $businessUuid,
            'name' => 'Compact Sticker',
            'width_mm' => 40,
            'height_mm' => 20,
            'show_product_name' => true,
            'show_price' => false,
            'show_barcode' => true,
            'show_qrcode' => false,
        ]);

        $service = app(LabelService::class);
        $preview = $service->previewLabel($product, $template);

        $this->assertEquals('Green Tea 500ml', $preview['content']['product_name']);
        $this->assertEquals('TEA-500', $preview['content']['sku']);
        $this->assertNull($preview['content']['price']);
        $this->assertNotNull($preview['rendered']['barcode_svg']);

        $log = $service->recordPrintLog($product, $template, 3);
        $this->assertEquals(3, $log->quantity_printed);
        $this->assertEquals($product->id, $log->product_id);
    }
}
