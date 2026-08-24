<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageService
    ) {}

    /**
     * Display a listing of categories with optional tree view, filters, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = $this->isGlobalAdmin($request);
        $businessUuid = $this->getBusinessUuid($request);

        $query = Category::query();
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
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('tree')) {
            $categories = $query->whereNull('parent_id')
                ->with(['children'])
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $categories = $query->with('parent')
            ->orderBy('sort_order')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context (business_uuid) is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();

        if ($request->hasFile('image_path')) {
            $data['image_path'] = $this->imageService->upload($request->file('image_path'), 'categories');
        } elseif ($request->hasFile('image')) {
            $data['image_path'] = $this->imageService->upload($request->file('image'), 'categories');
            unset($data['image']);
        }

        $category = Category::create(array_merge($data, [
            'business_uuid' => $businessUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified category with parent and child relationships.
     */
    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $category->load(['parent', 'children']),
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image_path')) {
            $this->imageService->delete($category->image_path);
            $data['image_path'] = $this->imageService->upload($request->file('image_path'), 'categories');
        } elseif ($request->hasFile('image')) {
            $this->imageService->delete($category->image_path);
            $data['image_path'] = $this->imageService->upload($request->file('image'), 'categories');
            unset($data['image']);
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $category->fresh(['parent', 'children']),
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->image_path) {
            $this->imageService->delete($category->image_path);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}
