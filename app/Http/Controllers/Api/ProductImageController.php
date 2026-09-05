<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageService
    ) {}

    /**
     * Display a listing of images for the specified product.
     */
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $product->images()->with('variant')->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Store a newly created product image in storage with webp/file upload support.
     */
    public function store(StoreProductImageRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context (business_uuid) is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->imageService->upload($request->file('image'), 'products');
            $data['disk'] = $data['disk'] ?? $this->imageService->disk();
            unset($data['image']);
        } elseif ($request->hasFile('image_path')) {
            $data['image_path'] = $this->imageService->upload($request->file('image_path'), 'products');
            $data['disk'] = $data['disk'] ?? $this->imageService->disk();
        }

        $image = ProductImage::create(array_merge($data, [
            'business_uuid' => $businessUuid,
            'product_id' => $product->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product image added successfully.',
            'data' => $image,
        ], Response::HTTP_CREATED);
    }

    /**
     * Remove the specified product image from storage.
     */
    public function destroy(ProductImage $image): JsonResponse
    {
        if ($image->image_path) {
            $this->imageService->delete($image->image_path);
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product image deleted successfully.',
        ]);
    }
}
