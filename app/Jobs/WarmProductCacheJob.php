<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductCode;
use App\Services\ProductScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmProductCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public ?string $businessUuid = null
    ) {}

    public function handle(ProductScanService $scanService): void
    {
        $businessUuids = $this->businessUuid 
            ? [$this->businessUuid]
            : Product::where('is_active', true)->distinct()->pluck('business_uuid')->toArray();

        $warmedCount = 0;

        foreach ($businessUuids as $bUuid) {
            // 1. Warm barcodes & QR codes
            $codes = ProductCode::where('business_uuid', $bUuid)
                ->where('is_active', true)
                ->pluck('code_value');

            foreach ($codes as $code) {
                if ($scanService->scan($code, $bUuid) !== null) {
                    $warmedCount++;
                }
            }

            // 2. Warm product SKUs
            $skus = Product::where('business_uuid', $bUuid)
                ->where('is_active', true)
                ->pluck('sku');

            foreach ($skus as $sku) {
                if ($scanService->scan($sku, $bUuid) !== null) {
                    $warmedCount++;
                }
            }
        }

        Log::info("WarmProductCacheJob completed: {$warmedCount} items cached across " . count($businessUuids) . " business(es).");
    }
}
