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

        $qrSvg = $service->renderQrCodeSvg('SP:PROD:550e8400-e29b-41d4-a716-446655440000');
        $this->assertStringContainsString('<svg', $qrSvg);
    }

    public function test_default_barcode_value_cleaning(): void
    {
        $service = app(ProductCodeService::class);

        $clean = $service->generateDefaultBarcodeValue('item #123 (red)');
        $this->assertEquals('ITEM123RED', $clean);
    }
}
