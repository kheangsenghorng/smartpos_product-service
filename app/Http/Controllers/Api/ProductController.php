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

    /**
     * Display a listing of products with filters, associations, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $this->isGlobalAdmin($request);
        $businessUuid = $this->getBusinessUuid($request);

        $query = Product::with([
            'category', 
            'brand', 
            'unit', 
            'currentPrice', 
            'primaryImage', 
            'codes', 
            'variants.currentPrice', 
            'variants.codes'
        ]);

        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        } elseif (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $products = $query->latest('id')->paginate($perPage);

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

    /**
     * Store a newly created product along with provisioning initial pricing and codes.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $userUuid = $this->getUserUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context (business_uuid) is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

    /**
     * Display the specified product with all relations and price history.
     */
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

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $userUuid = $this->getUserUuid($request);

        $product->update(array_merge($request->validated(), [
            'updated_by_uuid' => $userUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->fresh(['category', 'brand', 'unit', 'variants', 'codes', 'currentPrice']),
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
