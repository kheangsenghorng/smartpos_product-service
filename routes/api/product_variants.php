<?php

use App\Http\Controllers\Api\ProductVariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-variants')->group(function () {
    Route::put('/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/{variant}', [ProductVariantController::class, 'destroy'])->middleware('permission:products.delete');
});
