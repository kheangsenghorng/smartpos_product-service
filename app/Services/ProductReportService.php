<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductReport;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;

class ProductReportService
{
    public function __construct(
        protected ProductImportExportService $importExportService
    ) {}

    /**
     * Compute real-time catalog analytics and health summary.
     *
     * @return array<string, mixed>
     */
    public function getSummary(string $businessUuid): array
    {
        $baseQuery = Product::where('business_uuid', $businessUuid);

        $totalProducts = (clone $baseQuery)->count();
        $activeProducts = (clone $baseQuery)->where('is_active', true)->count();
        $inactiveProducts = $totalProducts - $activeProducts;
        $trackInventoryCount = (clone $baseQuery)->where('track_inventory', true)->count();
        $taxableCount = (clone $baseQuery)->where('is_taxable', true)->count();

        $totalVariants = ProductVariant::where('business_uuid', $businessUuid)->count();
        $productsWithVariants = (clone $baseQuery)->has('variants')->count();

        $productsWithoutBarcodes = (clone $baseQuery)->doesntHave('codes')->count();

        $totalCategories = Category::where('business_uuid', $businessUuid)->count();
        $totalBrands = Brand::where('business_uuid', $businessUuid)->count();

        $avgPrice = (float) ProductPrice::where('business_uuid', $businessUuid)
            ->where('is_active', true)
            ->whereNull('product_variant_id')
            ->avg('selling_price');

        $priceCount = ProductPrice::where('business_uuid', $businessUuid)
            ->where('is_active', true)
            ->count();

        return [
            'business_uuid' => $businessUuid,
            'catalog' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'inactive_products' => $inactiveProducts,
                'track_inventory_count' => $trackInventoryCount,
                'taxable_count' => $taxableCount,
                'total_variants' => $totalVariants,
                'products_with_variants' => $productsWithVariants,
                'products_without_barcodes' => $productsWithoutBarcodes,
            ],
            'taxonomy' => [
                'total_categories' => $totalCategories,
                'total_brands' => $totalBrands,
            ],
            'pricing' => [
                'active_price_points' => $priceCount,
                'average_selling_price' => round($avgPrice, 2),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate and store report file for a ProductReport model.
     */
    public function processReport(ProductReport $report): void
    {
        $report->update(['status' => 'processing']);

        try {
            $format = strtolower($report->format ?: 'csv');
            $type = strtolower($report->type ?: 'full_catalog');
            $businessUuid = $report->business_uuid;

            $filename = "reports/{$report->uuid}.{$format}";
            $disk = config('filesystems.default', 'public');

            $totalRecords = 0;

            if ($type === 'summary') {
                $data = $this->getSummary($businessUuid);
                $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $totalRecords = $data['catalog']['total_products'];
                Storage::disk($disk)->put($filename, $content);
            } else {
                // Full catalog or pricing sheet export
                $csv = $this->importExportService->export($businessUuid);
                $totalRecords = Product::where('business_uuid', $businessUuid)->count();
                Storage::disk($disk)->put($filename, $csv);
            }

            $report->update([
                'status' => 'completed',
                'file_path' => $filename,
                'total_records' => $totalRecords,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
