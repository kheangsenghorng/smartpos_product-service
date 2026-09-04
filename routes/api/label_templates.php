<?php

use App\Http\Controllers\Api\LabelPrintLogController;
use App\Http\Controllers\Api\LabelTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('label-templates')->group(function () {
    // 1. Label Print Audit Logs & Reprinting (Must be before /{template})
    Route::get('/logs', [LabelPrintLogController::class, 'index'])->middleware('permission:labels.view');
    Route::get('/logs/{log}', [LabelPrintLogController::class, 'show'])->middleware('permission:labels.view');
    Route::post('/logs/{log}/reprint', [LabelPrintLogController::class, 'reprint'])->middleware(['permission:labels.print', 'throttle:heavy-ops']);

    // 2. Template CRUD
    Route::get('/', [LabelTemplateController::class, 'index'])->middleware('permission:labels.view');
    Route::post('/', [LabelTemplateController::class, 'store'])->middleware('permission:labels.manage');
    Route::get('/{template}', [LabelTemplateController::class, 'show'])->middleware('permission:labels.view');
    Route::put('/{template}', [LabelTemplateController::class, 'update'])->middleware('permission:labels.manage');
    Route::delete('/{template}', [LabelTemplateController::class, 'destroy'])->middleware('permission:labels.manage');
});
