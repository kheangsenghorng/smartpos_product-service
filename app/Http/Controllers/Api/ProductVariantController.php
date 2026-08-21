<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Services\ProductCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProductVariantController extends Controller
{
    public function __construct(
        protected ProductCodeService $codeService
    ) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->variants()->with(['codes', 'prices', 'images'])->get(),
        ]);
    }

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $variant = DB::transaction(function () use ($request, $product, $businessUuid) {
            $data = $request->validated();

            $variant = ProductVariant::create([
                'business_uuid' => $businessUuid,
                'product_id' => $product->id,
                'name' => $data['name'],
                'sku' => $data['sku'],
                'sort_order' => $data['sort_order'] ?? 0,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['code_option']) && $data['code_option'] !== 'none') {
                $this->codeService->generateForVariant(
                    $variant,
                    $data['code_option'],
                    $data['barcode'] ?? null,
                    $data['qrcode'] ?? null
                );
            }

            if (isset($data['selling_price'])) {
                ProductPrice::create([
                    'business_uuid' => $businessUuid,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'currency_code' => $data['currency_code'] ?? 'USD',
                    'selling_price' => $data['selling_price'],
                    'cost_price' => $data['cost_price'] ?? null,
                    'minimum_price' => $data['minimum_price'] ?? null,
                    'is_active' => true,
                ]);
            }

            return $variant->load(['codes', 'prices']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Product variant created successfully.',
            'data' => $variant,
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): JsonResponse
    {
        $variant->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product variant updated successfully.',
            'data' => $variant->fresh(['codes', 'prices']),
        ]);
    }

    public function destroy(ProductVariant $variant): JsonResponse
    {
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product variant deleted successfully.',
        ]);
    }
}
