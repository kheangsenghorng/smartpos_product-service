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
    /**
     * Display a listing of price tiers for the specified product.
     */
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->prices()->with('variant')->latest('id')->get(),
        ]);
    }

    /**
     * Store a newly created price tier for the product in storage.
     */
    public function store(StoreProductPriceRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

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

    /**
     * Update the specified product price in storage.
     */
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

    /**
     * Remove the specified product price from storage.
     */
    public function destroy(ProductPrice $price): JsonResponse
    {
        $price->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product price deleted successfully.',
        ]);
    }
}
