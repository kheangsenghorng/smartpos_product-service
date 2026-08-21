<?php

use App\Http\Controllers\Api\LabelPrintController;
use App\Http\Controllers\Api\ProductCodeController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

    // Product Variants sub-routes
    Route::get('/{product}/variants', [ProductVariantController::class, 'index'])->middleware('permission:products.view');
    Route::post('/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:products.update');

    // Product Codes sub-routes
    Route::get('/{product}/codes', [ProductCodeController::class, 'index'])->middleware('permission:product_codes.view,products.view');
    Route::post('/{product}/codes', [ProductCodeController::class, 'store'])->middleware('permission:product_codes.create,products.update');
    Route::post('/{product}/codes/generate', [ProductCodeController::class, 'generate'])->middleware('permission:product_codes.create,products.update');

    // Product Prices sub-routes
    Route::get('/{product}/prices', [ProductPriceController::class, 'index'])->middleware('permission:product_prices.view,products.view');
    Route::post('/{product}/prices', [ProductPriceController::class, 'store'])->middleware('permission:product_prices.create,products.update');

    // Product Images sub-routes
    Route::get('/{product}/images', [ProductImageController::class, 'index'])->middleware('permission:product_images.view,products.view');
    Route::post('/{product}/images', [ProductImageController::class, 'store'])->middleware('permission:product_images.create,products.update');

    // Product Label Preview & Print sub-routes
    Route::post('/{product}/labels/preview', [LabelPrintController::class, 'preview'])->middleware('permission:labels.view,labels.print');
    Route::post('/{product}/labels/print', [LabelPrintController::class, 'print'])->middleware('permission:labels.print');
});
