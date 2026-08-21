<?php

use App\Http\Controllers\Api\LabelTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('label-templates')->group(function () {
    Route::get('/', [LabelTemplateController::class, 'index'])->middleware('permission:labels.view');
    Route::post('/', [LabelTemplateController::class, 'store'])->middleware('permission:labels.manage');
    Route::get('/{template}', [LabelTemplateController::class, 'show'])->middleware('permission:labels.view');
    Route::put('/{template}', [LabelTemplateController::class, 'update'])->middleware('permission:labels.manage');
    Route::delete('/{template}', [LabelTemplateController::class, 'destroy'])->middleware('permission:labels.manage');
});
