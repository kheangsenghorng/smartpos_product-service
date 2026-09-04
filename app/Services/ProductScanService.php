<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

class ProductScanService
{
    /**
     * Resolve a product or variant from a scanned barcode or SKU with Redis caching.
     *
     * @return array<string, mixed>|null
     */
    public function scan(string $code, string $businessUuid): ?array
    {
        $cleanCode = trim($code);
        if ($cleanCode === '') {
            return null;
        }

        $version = Cache::get("product_scan_ver:{$businessUuid}", 1);
        $cacheKey = "product_scan:{$businessUuid}:v{$version}:" . md5($cleanCode);

        return Cache::remember($cacheKey, 300, function () use ($cleanCode, $businessUuid) {
            return $this->resolveItem($cleanCode, $businessUuid);
        });
    }

    /**
     * Resolve barcode, QR code, variant SKU, or product SKU.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveItem(string $code, string $businessUuid): ?array
    {
        // 1. Check ProductCode table first (direct barcode / QR code scan)
        $productCode = ProductCode::where('business_uuid', $businessUuid)
            ->where('code_value', $code)
            ->where('is_active', true)
            ->with([
                'product.category',
                'product.brand',
                'product.unit',
                'product.currentPrice',
                'product.primaryImage',
                'variant.currentPrice',
            ])
            ->first();

        if ($productCode && $productCode->product && $productCode->product->is_active) {
            return $this->formatPayload(
                product: $productCode->product,
                variant: $productCode->variant,
                scannedCode: $code,
                codeType: $productCode->code_type ?? 'barcode'
            );
        }

        // 2. Check ProductVariant table by SKU
        $variant = ProductVariant::where('business_uuid', $businessUuid)
            ->where('sku', $code)
            ->where('is_active', true)
            ->with([
                'product.category',
                'product.brand',
                'product.unit',
                'product.currentPrice',
                'product.primaryImage',
                'currentPrice',
            ])
            ->first();

        if ($variant && $variant->product && $variant->product->is_active) {
            return $this->formatPayload(
                product: $variant->product,
                variant: $variant,
                scannedCode: $code,
                codeType: 'sku'
            );
        }

        // 3. Check Product table by SKU
        $product = Product::where('business_uuid', $businessUuid)
            ->where('sku', $code)
            ->where('is_active', true)
            ->with([
                'category',
                'brand',
                'unit',
                'currentPrice',
                'primaryImage',
            ])
            ->first();

        if ($product) {
            return $this->formatPayload(
                product: $product,
                variant: null,
                scannedCode: $code,
                codeType: 'sku'
            );
        }

        return null;
    }

    /**
     * Format a unified POS scanner payload.
     *
     * @return array<string, mixed>
     */
    protected function formatPayload(
        Product $product,
        ?ProductVariant $variant,
        string $scannedCode,
        string $codeType
    ): array {
        // Resolve active price: prefer variant currentPrice, fallback to product currentPrice
        $priceRecord = $variant?->currentPrice ?? $product->currentPrice;

        $sellingPrice = $priceRecord ? (float) $priceRecord->selling_price : 0.0;
        $costPrice = $priceRecord && !is_null($priceRecord->cost_price) ? (float) $priceRecord->cost_price : null;
        $currencyCode = $priceRecord?->currency_code ?? 'USD';

        $thumbnailUrl = $product->primaryImage?->image_url;

        return [
            'product_id' => $product->id,
            'product_uuid' => $product->uuid,
            'product_name' => $product->name,
            'variant_id' => $variant?->id,
            'variant_uuid' => $variant?->uuid,
            'variant_name' => $variant?->name,
            'sku' => $variant?->sku ?? $product->sku,
            'scanned_code' => $scannedCode,
            'code_type' => $codeType,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'code' => $product->category->code,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
            ] : null,
            'unit' => $product->unit ? [
                'id' => $product->unit->id,
                'name' => $product->unit->name,
                'symbol' => $product->unit->symbol,
                'precision' => (int) $product->unit->precision,
            ] : null,
            'price' => [
                'selling_price' => $sellingPrice,
                'cost_price' => $costPrice,
                'currency_code' => $currencyCode,
            ],
            'is_taxable' => (bool) $product->is_taxable,
            'track_inventory' => (bool) $product->track_inventory,
            'allow_negative_stock' => (bool) $product->allow_negative_stock,
            'thumbnail_url' => $thumbnailUrl,
        ];
    }
}
