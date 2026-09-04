<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Increment scan cache version for the business to invalidate all cached lookups.
     */
    public static function invalidateScanCache(?string $businessUuid): void
    {
        if ($businessUuid) {
            $key = "product_scan_ver:{$businessUuid}";
            if (Cache::has($key)) {
                Cache::increment($key);
            } else {
                Cache::forever($key, 2);
            }
        }
    }

    public function saved(Product $product): void
    {
        self::invalidateScanCache($product->business_uuid);
    }

    public function deleted(Product $product): void
    {
        self::invalidateScanCache($product->business_uuid);
    }

    public function restored(Product $product): void
    {
        self::invalidateScanCache($product->business_uuid);
    }

    public function forceDeleted(Product $product): void
    {
        self::invalidateScanCache($product->business_uuid);
    }
}
