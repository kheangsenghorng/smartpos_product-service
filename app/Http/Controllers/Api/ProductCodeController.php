<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductCodeRequest;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductVariant;
use App\Services\ProductCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductCodeController extends Controller
{
    public function __construct(
        protected ProductCodeService $codeService
    ) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->codes()->with('variant')->get(),
        ]);
    }

    public function store(StoreProductCodeRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $code = ProductCode::create(array_merge($request->validated(), [
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product code added successfully.',
            'data' => $code,
        ], Response::HTTP_CREATED);
    }

    public function generate(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'code_option' => ['required', 'string', 'in:barcode,qrcode,both'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'qrcode' => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($validated['product_variant_id'])) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->findOrFail($validated['product_variant_id']);

            $codes = $this->codeService->generateForVariant(
                $variant,
                $validated['code_option'],
                $validated['barcode'] ?? null,
                $validated['qrcode'] ?? null
            );
        } else {
            $codes = $this->codeService->generateForProduct(
                $product,
                $validated['code_option'],
                $validated['barcode'] ?? null,
                $validated['qrcode'] ?? null
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Product code(s) generated successfully.',
            'data' => $codes,
        ], Response::HTTP_CREATED);
    }

    public function destroy(ProductCode $code): JsonResponse
    {
        $code->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product code deleted successfully.',
        ]);
    }
}
