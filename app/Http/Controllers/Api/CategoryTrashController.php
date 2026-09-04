<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoryTrashController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageService
    ) {}

    /**
     * Display a listing of soft-deleted categories.
     */
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = Category::onlyTrashed()->latest('deleted_at');

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

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $categories = $query->paginate($perPage);

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
     * Restore a soft-deleted category.
     */
    public function restore(Request $request, string|int $id): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = Category::onlyTrashed();

        if (is_numeric($id)) {
            $query->where('id', $id);
        } else {
            $query->where('uuid', $id);
        }

        if ($businessUuid && !$isAdmin) {
            $query->where('business_uuid', $businessUuid);
        }

        $category = $query->firstOrFail();
        $category->restore();

        return response()->json([
            'success' => true,
            'message' => 'Category restored successfully.',
            'data' => $category->fresh(),
        ]);
    }

    /**
     * Permanently purge a soft-deleted category from storage.
     */
    public function forceDelete(Request $request, string|int $id): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = Category::onlyTrashed();

        if (is_numeric($id)) {
            $query->where('id', $id);
        } else {
            $query->where('uuid', $id);
        }

        if ($businessUuid && !$isAdmin) {
            $query->where('business_uuid', $businessUuid);
        }

        $category = $query->firstOrFail();

        if ($category->image_path) {
            $this->imageService->delete($category->image_path);
        }

        $category->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Category permanently deleted.',
        ]);
    }
}
