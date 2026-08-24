<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLabelTemplateRequest;
use App\Http\Requests\UpdateLabelTemplateRequest;
use App\Models\LabelTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LabelTemplateController extends Controller
{
    /**
     * Display a listing of label templates with optional search and active status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        $query = LabelTemplate::query();
        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->input('search')}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $templates = $query->latest('id')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    /**
     * Store a newly created label template in storage.
     */
    public function store(StoreLabelTemplateRequest $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context (business_uuid) is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $template = LabelTemplate::create(array_merge($request->validated(), [
            'business_uuid' => $businessUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Label template created successfully.',
            'data' => $template,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified label template.
     */
    public function show(LabelTemplate $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Update the specified label template in storage.
     */
    public function update(UpdateLabelTemplateRequest $request, LabelTemplate $template): JsonResponse
    {
        $template->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Label template updated successfully.',
            'data' => $template->fresh(),
        ]);
    }

    /**
     * Remove the specified label template from storage.
     */
    public function destroy(LabelTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Label template deleted successfully.',
        ]);
    }
}
