<?php

use App\Http\Controllers\Api\ProductImageController;
use Illuminate\Support\Facades\Route;

Route::prefix('product-images')->group(function () {
    Route::delete('/{image}', [ProductImageController::class, 'destroy'])->middleware('permission:product_images.delete,products.update');
});
