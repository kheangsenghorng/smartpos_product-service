<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductProvisionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductProvisionService $provisionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $query = Product::where('business_uuid', $businessUuid)
            ->with(['category', 'brand', 'unit', 'currentPrice', 'primaryImage', 'codes', 'variants.currentPrice', 'variants.codes']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhereHas('codes', function ($cq) use ($search) {
                      $cq->where('code_value', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('sku')) {
            $query->where('sku', $request->input('sku'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('available_only')) {
            $today = Carbon::today()->toDateString();
            $query->where('is_active', true)
                ->where(function ($q) use ($today) {
                    $q->whereNull('available_from')->orWhere('available_from', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('available_until')->orWhere('available_until', '>=', $today);
                });
        }

        $products = $query->latest('id')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');
        $userUuid = $request->attributes->get('auth_user_uuid');

        $product = $this->provisionService->createProduct(
            $request->validated(),
            $businessUuid,
            $userUuid
        );

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], Response::HTTP_CREATED);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'category',
            'brand',
            'unit',
            'variants.codes',
            'variants.prices',
            'variants.images',
            'codes',
            'prices',
            'images',
        ]);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $userUuid = $request->attributes->get('auth_user_uuid');

        $product->update(array_merge($request->validated(), [
            'updated_by_uuid' => $userUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->fresh(['category', 'brand', 'unit', 'variants', 'codes', 'currentPrice']),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
