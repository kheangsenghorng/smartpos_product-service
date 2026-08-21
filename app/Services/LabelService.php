<?php

namespace App\Services;

use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductLabelPrintLog;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LabelService
{
    public function __construct(
        protected ProductCodeService $codeService
    ) {}

    /**
     * Build preview payload and rendered SVG/HTML snippets for a product sticker label.
     */
    public function previewLabel(Product $product, LabelTemplate $template, ?ProductVariant $variant = null): array
    {
        $productName = $product->name;
        $variantName = $variant ? $variant->name : null;
        $sku = $variant ? $variant->sku : $product->sku;

        // Current price
        $priceRecord = $variant ? $variant->currentPrice : $product->currentPrice;
        $priceFormatted = $priceRecord
            ? ($priceRecord->currency_code . ' ' . number_format((float) $priceRecord->selling_price, 2))
            : 'N/A';

        // Barcode / QR Codes
        $codes = $variant ? $variant->codes : $product->codes;
        $barcodeCode = $codes->firstWhere('code_type', 'barcode') ?: $product->codes->firstWhere('code_type', 'barcode');
        $qrcodeCode = $codes->firstWhere('code_type', 'qrcode') ?: $product->codes->firstWhere('code_type', 'qrcode');

        $barcodeValue = $barcodeCode ? $barcodeCode->code_value : $sku;
        $qrcodeValue = $qrcodeCode ? $qrcodeCode->code_value : "SP:PROD:{$product->uuid}";

        $barcodeSvg = null;
        if ($template->show_barcode && $barcodeValue) {
            try {
                $barcodeSvg = $this->codeService->renderBarcodeSvg($barcodeValue, $barcodeCode?->symbology ?? 'CODE128');
            } catch (\Throwable $e) {
                $barcodeSvg = null;
            }
        }

        $qrcodeSvg = null;
        if ($template->show_qrcode && $qrcodeValue) {
            try {
                $qrcodeSvg = $this->codeService->renderQrCodeSvg($qrcodeValue, 120);
            } catch (\Throwable $e) {
                $qrcodeSvg = null;
            }
        }

        return [
            'template' => [
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'width_mm' => (float) $template->width_mm,
                'height_mm' => (float) $template->height_mm,
            ],
            'content' => [
                'product_name' => $template->show_product_name ? $productName : null,
                'variant_name' => ($template->show_variant_name && $variantName) ? $variantName : null,
                'sku' => $template->show_sku ? $sku : null,
                'price' => $template->show_price ? $priceFormatted : null,
                'barcode_value' => $template->show_barcode ? $barcodeValue : null,
                'qrcode_value' => $template->show_qrcode ? $qrcodeValue : null,
            ],
            'rendered' => [
                'barcode_svg' => $barcodeSvg,
                'qrcode_svg' => $qrcodeSvg,
            ],
        ];
    }

    /**
     * Execute print action and create an audit print log.
     */
    public function recordPrintLog(
        Product $product,
        LabelTemplate $template,
        int $quantity = 1,
        ?ProductVariant $variant = null,
        ?string $printedByUuid = null
    ): ProductLabelPrintLog {
        return ProductLabelPrintLog::create([
            'business_uuid' => $product->business_uuid,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'label_template_id' => $template->id,
            'printed_by_uuid' => $printedByUuid,
            'quantity_printed' => max(1, $quantity),
            'printed_at' => Carbon::now(),
        ]);
    }
}
