<?php

namespace Tests\Feature;

use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductLabelPrintLog;
use App\Models\Unit;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelPrintLogApiTest extends TestCase
{
    public function test_can_list_print_logs_with_pagination(): void
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
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-1',
            'is_active' => true,
        ]);

        $template = LabelTemplate::create([
            'business_uuid' => $businessUuid,
            'name' => '40x30mm Standard',
            'width_mm' => 40.0,
            'height_mm' => 30.0,
            'is_active' => true,
        ]);

        ProductLabelPrintLog::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'label_template_id' => $template->id,
            'quantity_printed' => 5,
            'printed_at' => now(),
        ]);

        $response = $this->actingAsJwt($businessUuid)
            ->getJson('/api/v1/label-templates/logs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.quantity_printed', 5);
    }

    public function test_can_show_and_reprint_label_log(): void
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
            'name' => 'Reprint Product',
            'sku' => 'REPRINT-SKU',
            'is_active' => true,
        ]);

        $template = LabelTemplate::create([
            'business_uuid' => $businessUuid,
            'name' => '50x25mm Barcode Label',
            'width_mm' => 50.0,
            'height_mm' => 25.0,
            'is_active' => true,
        ]);

        $log = ProductLabelPrintLog::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'label_template_id' => $template->id,
            'quantity_printed' => 2,
            'printed_at' => now(),
        ]);

        // Show print log
        $showResponse = $this->actingAsJwt($businessUuid)
            ->getJson("/api/v1/label-templates/logs/{$log->id}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.log.id', $log->id);

        // Reprint label
        $reprintResponse = $this->actingAsJwt($businessUuid)
            ->postJson("/api/v1/label-templates/logs/{$log->id}/reprint", [
                'quantity' => 10,
            ]);

        $reprintResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.log.quantity_printed', 10);

        $this->assertDatabaseCount('product_label_print_logs', 2);
    }
}
