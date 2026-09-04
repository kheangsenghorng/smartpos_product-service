<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLabelPrintLog;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LabelPrintLogController extends Controller
{
    public function __construct(
        protected LabelService $labelService
    ) {}

    /**
     * Display a paginated listing of label print logs.
     */
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = ProductLabelPrintLog::with(['product', 'variant', 'template'])->latest('id');

        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        } elseif (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('label_template_id')) {
            $query->where('label_template_id', $request->input('label_template_id'));
        }

        if ($request->filled('printed_by_uuid')) {
            $query->where('printed_by_uuid', $request->input('printed_by_uuid'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('printed_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('printed_at', '<=', $request->input('to_date'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Display the specified print log.
     */
    public function show(Request $request, ProductLabelPrintLog $log): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        if (!$isAdmin && $businessUuid && $log->business_uuid !== $businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to print log.',
            ], Response::HTTP_FORBIDDEN);
        }

        $log->load(['product', 'variant', 'template']);

        $preview = null;
        if ($log->product && $log->template) {
            $preview = $this->labelService->previewLabel(
                $log->product,
                $log->template,
                $log->variant
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'log' => $log,
                'preview' => $preview,
            ],
        ]);
    }

    /**
     * Re-dispatch print job based on an existing print log.
     */
    public function reprint(Request $request, ProductLabelPrintLog $log): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $userUuid = $this->getUserUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        if (!$isAdmin && $businessUuid && $log->business_uuid !== $businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to print log.',
            ], Response::HTTP_FORBIDDEN);
        }

        $log->load(['product', 'variant', 'template']);

        if (!$log->product || !$log->template) {
            return response()->json([
                'success' => false,
                'message' => 'Associated product or label template no longer exists.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $quantity = (int) $request->input('quantity', $log->quantity_printed ?: 1);

        $newLog = $this->labelService->recordPrintLog(
            $log->product,
            $log->template,
            $quantity,
            $log->variant,
            $userUuid
        );

        $preview = $this->labelService->previewLabel(
            $log->product,
            $log->template,
            $log->variant
        );

        return response()->json([
            'success' => true,
            'message' => "Label reprinted successfully for {$quantity} item(s).",
            'data' => [
                'log' => $newLog,
                'preview' => $preview,
            ],
        ], Response::HTTP_CREATED);
    }
}
