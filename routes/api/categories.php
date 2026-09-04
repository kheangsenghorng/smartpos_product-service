<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryTrashController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->group(function () {
    // Trash & Restore Management (Must be before /{category})
    Route::get('/trash', [CategoryTrashController::class, 'index'])->middleware('permission:categories.delete,categories.view');
    Route::post('/{id}/restore', [CategoryTrashController::class, 'restore'])->middleware('permission:categories.delete,categories.update');
    Route::delete('/{id}/force', [CategoryTrashController::class, 'forceDelete'])->middleware('permission:categories.delete');

    // Core Category CRUD
    Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view');
    Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create');
    Route::get('/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
    Route::put('/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');
});
