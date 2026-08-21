<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductPriceRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductPriceController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->prices()->with('variant')->latest('id')->get(),
        ]);
    }

    public function store(StoreProductPriceRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $price = ProductPrice::create(array_merge($request->validated(), [
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product price added successfully.',
            'data' => $price,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, ProductPrice $price): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $price->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product price updated successfully.',
            'data' => $price->fresh(),
        ]);
    }

    public function destroy(ProductPrice $price): JsonResponse
    {
        $price->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product price deleted successfully.',
        ]);
    }
}
