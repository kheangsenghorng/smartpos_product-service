<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->images()->with('variant')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $image = ProductImage::create(array_merge($request->validated(), [
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product image added successfully.',
            'data' => $image,
        ], Response::HTTP_CREATED);
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product image deleted successfully.',
        ]);
    }
}
