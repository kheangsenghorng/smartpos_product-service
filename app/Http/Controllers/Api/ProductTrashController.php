<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductTrashController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageService
    ) {}

    /**
     * Display a listing of soft-deleted products.
     */
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = Product::onlyTrashed()
            ->with(['category', 'brand', 'unit', 'currentPrice'])
            ->latest('deleted_at');

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
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $products = $query->paginate($perPage);

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
     * Restore a soft-deleted product.
     */
    public function restore(Request $request, string|int $id): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = Product::onlyTrashed();

        if (is_numeric($id)) {
            $query->where('id', $id);
        } else {
            $query->where('uuid', $id);
        }

        if ($businessUuid && !$isAdmin) {
            $query->where('business_uuid', $businessUuid);
        }

        $product = $query->firstOrFail();
        $product->restore();

        return response()->json([
            'success' => true,
            'message' => 'Product restored successfully.',
            'data' => $product->fresh(['category', 'brand', 'unit', 'variants', 'codes']),
        ]);
    }

    /**
     * Permanently purge a soft-deleted product from storage.
     */
    public function forceDelete(Request $request, string|int $id): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = Product::onlyTrashed()->with('images');

        if (is_numeric($id)) {
            $query->where('id', $id);
        } else {
            $query->where('uuid', $id);
        }

        if ($businessUuid && !$isAdmin) {
            $query->where('business_uuid', $businessUuid);
        }

        $product = $query->firstOrFail();

        // Clean up attached images from S3 storage
        foreach ($product->images as $image) {
            if ($image->image_path) {
                $this->imageService->delete($image->image_path);
            }
        }

        $product->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Product permanently deleted.',
        ]);
    }
}
