<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductVariant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGeneratorSVG;

class ProductCodeService
{
    /**
     * Create product codes based on the selected code_option.
     * code_option: none | barcode | qrcode | both
     *
     * @return array<ProductCode>
     */
    public function generateForProduct(Product $product, string $codeOption = 'none', ?string $customBarcode = null, ?string $customQrcode = null): array
    {
        $createdCodes = [];

        if ($codeOption === 'barcode' || $codeOption === 'both') {
            $barcodeValue = $customBarcode ?: $this->generateDefaultBarcodeValue($product->sku);
            $createdCodes[] = ProductCode::create([
                'business_uuid' => $product->business_uuid,
                'product_id' => $product->id,
                'product_variant_id' => null,
                'code_type' => 'barcode',
                'symbology' => 'CODE128',
                'code_value' => $barcodeValue,
                'is_primary' => true,
                'is_auto_generated' => empty($customBarcode),
                'is_active' => true,
            ]);
        }

        if ($codeOption === 'qrcode' || $codeOption === 'both') {
            $qrValue = $customQrcode ?: "SP:PROD:{$product->uuid}";
            $createdCodes[] = ProductCode::create([
                'business_uuid' => $product->business_uuid,
                'product_id' => $product->id,
                'product_variant_id' => null,
                'code_type' => 'qrcode',
                'symbology' => 'QR',
                'code_value' => $qrValue,
                'is_primary' => ($codeOption === 'qrcode'),
                'is_auto_generated' => empty($customQrcode),
                'is_active' => true,
            ]);
        }

        return $createdCodes;
    }

    /**
     * Create variant codes based on the selected code_option.
     *
     * @return array<ProductCode>
     */
    public function generateForVariant(ProductVariant $variant, string $codeOption = 'none', ?string $customBarcode = null, ?string $customQrcode = null): array
    {
        $createdCodes = [];

        if ($codeOption === 'barcode' || $codeOption === 'both') {
            $barcodeValue = $customBarcode ?: $this->generateDefaultBarcodeValue($variant->sku);
            $createdCodes[] = ProductCode::create([
                'business_uuid' => $variant->business_uuid,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'code_type' => 'barcode',
                'symbology' => 'CODE128',
                'code_value' => $barcodeValue,
                'is_primary' => true,
                'is_auto_generated' => empty($customBarcode),
                'is_active' => true,
            ]);
        }

        if ($codeOption === 'qrcode' || $codeOption === 'both') {
            $qrValue = $customQrcode ?: "SP:VAR:{$variant->uuid}";
            $createdCodes[] = ProductCode::create([
                'business_uuid' => $variant->business_uuid,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'code_type' => 'qrcode',
                'symbology' => 'QR',
                'code_value' => $qrValue,
                'is_primary' => ($codeOption === 'qrcode'),
                'is_auto_generated' => empty($customQrcode),
                'is_active' => true,
            ]);
        }

        return $createdCodes;
    }

    /**
     * Render SVG string for a barcode.
     */
    public function renderBarcodeSvg(string $codeValue, string $symbology = 'CODE128'): string
    {
        $generator = new BarcodeGeneratorSVG();
        $type = match (strtoupper($symbology)) {
            'EAN13' => $generator::TYPE_EAN_13,
            'EAN8' => $generator::TYPE_EAN_8,
            'UPC_A' => $generator::TYPE_UPC_A,
            'CODE39' => $generator::TYPE_CODE_39,
            default => $generator::TYPE_CODE_128,
        };

        return $generator->getBarcode($codeValue, $type);
    }

    /**
     * Render SVG string for a QR code.
     */
    public function renderQrCodeSvg(string $codeValue, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        return $writer->writeString($codeValue);
    }

    public function generateDefaultBarcodeValue(string $sku): string
    {
        // Clean and uppercase SKU for code128 compatibility
        $clean = preg_replace('/[^A-Za-z0-9\-]/', '', strtoupper($sku));
        return $clean ?: 'ITEM-' . strtoupper(substr(uniqid(), -6));
    }
}
