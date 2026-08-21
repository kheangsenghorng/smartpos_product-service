<?php

use App\Http\Controllers\Api\ProductPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-prices')->group(function () {
    Route::put('/{price}', [ProductPriceController::class, 'update'])->middleware('permission:product_prices.update,products.update');
    Route::delete('/{price}', [ProductPriceController::class, 'destroy'])->middleware('permission:product_prices.update,products.update');
});
