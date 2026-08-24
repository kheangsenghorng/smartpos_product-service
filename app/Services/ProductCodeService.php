<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductVariant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;
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
     * Render barcode as SVG with human-readable text support.
     *
     * Example:
     *  ███ ██ █ ████ ███
     *      8850000000010
     */
    public function renderBarcodeSvg(
        string $value,
        string $symbology = 'CODE128',
        bool $showText = true,
        int $barWidth = 2,
        int $barHeight = 60
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Barcode value cannot be empty.');
        }

        $generator = new BarcodeGeneratorSVG();
        $type = $this->resolveBarcodeType($generator, $symbology);

        $svg = $generator->getBarcode($value, $type, $barWidth, $barHeight);

        if (!$showText) {
            return $svg;
        }

        return $this->addReadableBarcodeText($svg, $value, $barHeight);
    }

    /**
     * Resolve symbology name to Picqer Barcode generator type.
     */
    public function resolveBarcodeType(
        BarcodeGeneratorSVG $generator,
        string $symbology
    ): string {
        return match (strtoupper(trim($symbology))) {
            'EAN13', 'EAN-13' => $generator::TYPE_EAN_13,
            'EAN8', 'EAN-8' => $generator::TYPE_EAN_8,
            'UPCA', 'UPC-A', 'UPC_A' => $generator::TYPE_UPC_A,
            'CODE39', 'CODE-39' => $generator::TYPE_CODE_39,
            'CODE128', 'CODE-128' => $generator::TYPE_CODE_128,
            default => $generator::TYPE_CODE_128,
        };
    }

    /**
     * Add human-readable barcode value underneath barcode bars.
     */
    private function addReadableBarcodeText(
        string $svg,
        string $value,
        int $barHeight
    ): string {
        $width = 250;

        if (preg_match('/<svg[^>]*\swidth="([^"]+)"/i', $svg, $matches)) {
            $width = (float) preg_replace('/[^0-9.]/', '', $matches[1]);
        }

        $textAreaHeight = 30;
        $totalHeight = $barHeight + $textAreaHeight;

        // Replace root SVG height using preg_replace_callback to avoid $1 digit ambiguity
        $svg = preg_replace_callback(
            '/(<svg\b[^>]*\bheight=")([^"]*)(")/i',
            fn(array $matches) => $matches[1] . $totalHeight . $matches[3],
            $svg,
            1
        );

        // Update viewBox if the barcode library generated one
        if (preg_match('/viewBox="([^"]+)"/i', $svg)) {
            $svg = preg_replace_callback(
                '/viewBox="([^"]+)"/i',
                function (array $matches) use ($totalHeight) {
                    $parts = preg_split('/\s+/', trim($matches[1]));

                    if (count($parts) === 4) {
                        $parts[3] = (string) $totalHeight;

                        return 'viewBox="' . implode(' ', $parts) . '"';
                    }

                    return $matches[0];
                },
                $svg,
                1
            );
        }

        $safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $textY = $barHeight + 22;

        $text = sprintf(
            '<text x="%s" y="%s" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="18" letter-spacing="2" fill="#000000">%s</text>',
            $width / 2,
            $textY,
            $safeValue
        );

        return str_replace('</svg>', $text . '</svg>', $svg);
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
        $clean = preg_replace('/[^A-Za-z0-9\-]/', '', strtoupper($sku));
        return $clean ?: 'ITEM-' . strtoupper(substr(uniqid(), -6));
    }
}
