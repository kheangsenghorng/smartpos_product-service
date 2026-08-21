<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LabelPrintController;
use App\Http\Controllers\Api\LabelTemplateController;
use App\Http\Controllers\Api\ProductCodeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\UnitController;
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

Route::prefix('v1')->middleware(['jwt.auth', 'product.access'])->group(function () {

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

    // Brands
    Route::get('/brands', [BrandController::class, 'index'])->middleware('permission:brands.view');
    Route::post('/brands', [BrandController::class, 'store'])->middleware('permission:brands.create');
    Route::get('/brands/{brand}', [BrandController::class, 'show'])->middleware('permission:brands.view');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.update');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete');

    // Units
    Route::get('/units', [UnitController::class, 'index'])->middleware('permission:units.view');
    Route::post('/units', [UnitController::class, 'store'])->middleware('permission:units.create');
    Route::get('/units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.view');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.delete');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

    // Product Variants
    Route::get('/products/{product}/variants', [ProductVariantController::class, 'index'])->middleware('permission:products.view');
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:products.update');
    Route::put('/product-variants/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/product-variants/{variant}', [ProductVariantController::class, 'destroy'])->middleware('permission:products.delete');

    // Product Codes (Barcode & QR Code)
    Route::get('/products/{product}/codes', [ProductCodeController::class, 'index'])->middleware('permission:product_codes.view,products.view');
    Route::post('/products/{product}/codes', [ProductCodeController::class, 'store'])->middleware('permission:product_codes.create,products.update');
    Route::post('/products/{product}/codes/generate', [ProductCodeController::class, 'generate'])->middleware('permission:product_codes.create,products.update');
    Route::delete('/product-codes/{code}', [ProductCodeController::class, 'destroy'])->middleware('permission:product_codes.delete,products.update');

    // Product Prices
    Route::get('/products/{product}/prices', [ProductPriceController::class, 'index'])->middleware('permission:product_prices.view,products.view');
    Route::post('/products/{product}/prices', [ProductPriceController::class, 'store'])->middleware('permission:product_prices.create,products.update');
    Route::put('/product-prices/{price}', [ProductPriceController::class, 'update'])->middleware('permission:product_prices.update,products.update');
    Route::delete('/product-prices/{price}', [ProductPriceController::class, 'destroy'])->middleware('permission:product_prices.update,products.update');

    // Product Images
    Route::get('/products/{product}/images', [ProductImageController::class, 'index'])->middleware('permission:product_images.view,products.view');
    Route::post('/products/{product}/images', [ProductImageController::class, 'store'])->middleware('permission:product_images.create,products.update');
    Route::delete('/product-images/{image}', [ProductImageController::class, 'destroy'])->middleware('permission:product_images.delete,products.update');

    // Label Templates
    Route::get('/label-templates', [LabelTemplateController::class, 'index'])->middleware('permission:labels.view');
    Route::post('/label-templates', [LabelTemplateController::class, 'store'])->middleware('permission:labels.manage');
    Route::get('/label-templates/{template}', [LabelTemplateController::class, 'show'])->middleware('permission:labels.view');
    Route::put('/label-templates/{template}', [LabelTemplateController::class, 'update'])->middleware('permission:labels.manage');
    Route::delete('/label-templates/{template}', [LabelTemplateController::class, 'destroy'])->middleware('permission:labels.manage');

    // Label Preview & Print
    Route::post('/products/{product}/labels/preview', [LabelPrintController::class, 'preview'])->middleware('permission:labels.view,labels.print');
    Route::post('/products/{product}/labels/print', [LabelPrintController::class, 'print'])->middleware('permission:labels.print');
});
