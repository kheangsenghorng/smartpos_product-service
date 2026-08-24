<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageService
    ) {}

    /**
     * Display a listing of brands with optional search and active status filters.
     * Admins can view all brands across businesses or filter by specific business_uuid.
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $this->isGlobalAdmin($request);
        $businessUuid = $this->getBusinessUuid($request);

        $query = Brand::query();

        // Filter by business_uuid if provided, or if the user is not a global admin
        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        } elseif (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required. Please provide business_uuid in the request or token.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $brands = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $brands->items(),
            'meta' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
            ],
        ]);
    }

    /**
     * Store a newly created brand in storage with image upload support.
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context (business_uuid) is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();

        // Handle image upload (.webp, .png, .jpg, etc.)
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->imageService->upload($request->file('logo'), 'brands');
            unset($data['logo']);
        }

        $brand = Brand::create(array_merge($data, [
            'business_uuid' => $businessUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully.',
            'data' => $brand,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $brand,
        ]);
    }

    /**
     * Update the specified brand in storage with image replacement support.
     */
    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->validated();

        // Handle image replacement (.webp, .png, etc.)
        if ($request->hasFile('logo')) {
            $this->imageService->delete($brand->logo_path);
            $data['logo_path'] = $this->imageService->upload($request->file('logo'), 'brands');
            unset($data['logo']);
        }

        $brand->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data' => $brand->fresh(),
        ]);
    }

    /**
     * Remove the specified brand from storage.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->logo_path) {
            $this->imageService->delete($brand->logo_path);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully.',
        ]);
    }
}
