<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/product', function () {
    return redirect('/docs/products');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'smartpos-product-service',
        'port' => 8003,
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/product/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'smartpos-product-service',
        'port' => 8003,
        'timestamp' => now()->toIso8601String(),
    ]);
});

