<?php

use App\Http\Controllers\Api\BrandController;
use Illuminate\Support\Facades\Route;

Route::prefix('brands')->group(function () {
    Route::get('/', [BrandController::class, 'index'])->middleware('permission:brands.view');
    Route::post('/', [BrandController::class, 'store'])->middleware('permission:brands.create');
    Route::get('/{brand}', [BrandController::class, 'show'])->middleware('permission:brands.view');
    Route::put('/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.update');
    Route::delete('/{brand}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete');
});
