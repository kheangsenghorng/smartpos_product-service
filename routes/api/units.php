<?php

use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('units')->group(function () {
    Route::get('/', [UnitController::class, 'index'])->middleware('permission:units.view');
    Route::post('/', [UnitController::class, 'store'])->middleware('permission:units.create');
    Route::get('/{unit}', [UnitController::class, 'show'])->middleware('permission:units.view');
    Route::put('/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update');
    Route::delete('/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.delete');
});
