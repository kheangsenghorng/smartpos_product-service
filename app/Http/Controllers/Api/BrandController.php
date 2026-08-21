<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $query = Brand::where('business_uuid', $businessUuid);

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

        $brands = $query->latest('id')->paginate($request->input('per_page', 20));

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

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $brand = Brand::create(array_merge($request->validated(), [
            'business_uuid' => $businessUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully.',
            'data' => $brand,
        ], Response::HTTP_CREATED);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data' => $brand->fresh(),
        ]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully.',
        ]);
    }
}
