<?php

use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view');
    Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create');
    Route::get('/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
    Route::put('/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');
});
