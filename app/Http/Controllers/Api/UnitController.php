<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $query = Unit::where('business_uuid', $businessUuid);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $units = $query->orderBy('name')->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $units->items(),
            'meta' => [
                'current_page' => $units->currentPage(),
                'last_page' => $units->lastPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
            ],
        ]);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');

        $unit = Unit::create(array_merge($request->validated(), [
            'business_uuid' => $businessUuid,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Unit created successfully.',
            'data' => $unit,
        ], Response::HTTP_CREATED);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $unit,
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Unit updated successfully.',
            'data' => $unit->fresh(),
        ]);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete unit because it is currently assigned to products.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit deleted successfully.',
        ]);
    }
}
