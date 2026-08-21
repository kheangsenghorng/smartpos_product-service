<?php

use App\Http\Controllers\Api\ProductCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-codes')->group(function () {
    Route::delete('/{code}', [ProductCodeController::class, 'destroy'])->middleware('permission:product_codes.delete,products.update');
});
