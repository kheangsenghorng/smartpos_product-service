<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SmartPOS Product Service API Routes (Port :8003)
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'smartpos-product-service',
        'port' => 8003,
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/product/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'smartpos-product-service',
        'port' => 8003,
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {
    // Process Report Documentation (Protected by jwt.auth)
    Route::middleware(['throttle:api', 'jwt.auth'])->get('/PRODUCT_SERVICE_PROCESS_REPORT.md', function () {
        $path = base_path('PRODUCT_SERVICE_PROCESS_REPORT.md');
        if (!file_exists($path)) {
            abort(404, 'Process report file not found.');
        }
        return response()->file($path, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="PRODUCT_SERVICE_PROCESS_REPORT.md"',
        ]);
    });

    Route::middleware(['throttle:api', 'jwt.auth', 'product.access'])->group(function () {
        require __DIR__ . '/api/categories.php';
    require __DIR__ . '/api/brands.php';
    require __DIR__ . '/api/units.php';
    require __DIR__ . '/api/products.php';
    require __DIR__ . '/api/product_variants.php';
    require __DIR__ . '/api/product_codes.php';
    require __DIR__ . '/api/product_prices.php';
    require __DIR__ . '/api/product_images.php';
    require __DIR__ . '/api/label_templates.php';
    });
});

