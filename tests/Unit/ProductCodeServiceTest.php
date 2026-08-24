<?php

namespace Tests\Unit;

use App\Services\ProductCodeService;
use Tests\TestCase;

class ProductCodeServiceTest extends TestCase
{
    public function test_barcode_and_qr_svg_generation(): void
    {
        $service = app(ProductCodeService::class);

        $barcodeSvg = $service->renderBarcodeSvg('COKE-330', 'CODE128');
        $this->assertStringContainsString('<svg', $barcodeSvg);
        $this->assertStringContainsString('COKE-330', $barcodeSvg);

        $qrSvg = $service->renderQrCodeSvg('SP:PROD:550e8400-e29b-41d4-a716-446655440000');
        $this->assertStringContainsString('<svg', $qrSvg);
    }

    public function test_barcode_with_and_without_text(): void
    {
        $service = app(ProductCodeService::class);

        $barcodeWithText = $service->renderBarcodeSvg('8850000000010', 'CODE128', true);
        $this->assertStringContainsString('<text', $barcodeWithText);
        $this->assertStringContainsString('8850000000010', $barcodeWithText);

        $barcodeWithoutText = $service->renderBarcodeSvg('8850000000010', 'CODE128', false);
        $this->assertStringNotContainsString('<text', $barcodeWithoutText);
    }

    public function test_default_barcode_value_cleaning(): void
    {
        $service = app(ProductCodeService::class);

        $clean = $service->generateDefaultBarcodeValue('item #123 (red)');
        $this->assertEquals('ITEM123RED', $clean);
    }
}
