<?php

namespace Tests\Feature;

use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Unit;
use App\Services\ProductCodeService;
use Illuminate\Support\Str;
use Tests\TestCase;

class LabelTemplateAndPrintTest extends TestCase
{
    public function test_can_create_and_manage_label_template(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/label-templates', [
            'name' => 'Standard Shelf Label',
            'width_mm' => 50.0,
            'height_mm' => 30.0,
            'show_product_name' => true,
            'show_price' => true,
            'show_barcode' => true,
            'show_qrcode' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Standard Shelf Label')
            ->assertJsonPath('data.width_mm', '50.00');

        $templateId = $response->json('data.id');

        $this->assertDatabaseHas('label_templates', [
            'id' => $templateId,
            'business_uuid' => $businessUuid,
        ]);
    }

    public function test_can_preview_and_print_product_label(): void
    {
        $businessUuid = (string) Str::uuid();
        $userUuid = (string) Str::uuid();

        $unit = Unit::create([
            'business_uuid' => $businessUuid,
            'name' => 'Piece',
            'code' => 'PCS',
            'symbol' => 'pcs',
        ]);

        $product = Product::create([
            'business_uuid' => $businessUuid,
            'unit_id' => $unit->id,
            'name' => 'Coca-Cola 330ml',
            'sku' => 'COKE-330',
        ]);

        // Code and price
        app(ProductCodeService::class)->generateForProduct($product, 'both');

        ProductPrice::create([
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'currency_code' => 'USD',
            'selling_price' => 0.75,
            'is_active' => true,
        ]);

        $template = LabelTemplate::create([
            'business_uuid' => $businessUuid,
            'name' => 'Barcode + QR Template',
            'width_mm' => 50,
            'height_mm' => 30,
            'show_product_name' => true,
            'show_price' => true,
            'show_barcode' => true,
            'show_qrcode' => true,
            'is_active' => true,
        ]);

        // 1. Preview
        $previewResponse = $this->actingAsJwt($businessUuid, $userUuid)
            ->postJson("/api/v1/products/{$product->id}/labels/preview", [
                'label_template_id' => $template->id,
            ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content.product_name', 'Coca-Cola 330ml')
            ->assertJsonPath('data.content.price', 'USD 0.75');

        $this->assertNotEmpty($previewResponse->json('data.rendered.barcode_svg'));
        $this->assertNotEmpty($previewResponse->json('data.rendered.qrcode_svg'));

        // 2. Print
        $printResponse = $this->actingAsJwt($businessUuid, $userUuid)
            ->postJson("/api/v1/products/{$product->id}/labels/print", [
                'label_template_id' => $template->id,
                'quantity' => 5,
            ]);

        $printResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.log.quantity_printed', 5);

        $this->assertDatabaseHas('product_label_print_logs', [
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
            'label_template_id' => $template->id,
            'quantity_printed' => 5,
            'printed_by_uuid' => $userUuid,
        ]);
    }
}
