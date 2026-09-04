<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateProductReportJob;
use App\Models\ProductReport;
use App\Services\ProductReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProductReportController extends Controller
{
    public function __construct(
        protected ProductReportService $reportService
    ) {}

    /**
     * Get real-time catalog health and statistical summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $summary = $this->reportService->getSummary($businessUuid);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * List all generated catalog reports for the business.
     */
    public function index(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        $query = ProductReport::latest('id');

        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        } elseif (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $reports = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Dispatch a background job to generate a catalog report.
     */
    public function generate(Request $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $userUuid = $this->getUserUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:full_catalog,summary,pricing_sheet,inventory_audit'],
            'format' => ['nullable', 'string', 'in:csv,json'],
        ]);

        $type = $validated['type'] ?? 'full_catalog';
        $format = $validated['format'] ?? 'csv';
        $name = $validated['name'] ?? ('Catalog Report (' . strtoupper($type) . ')');

        $report = ProductReport::create([
            'business_uuid' => $businessUuid,
            'user_uuid' => $userUuid,
            'name' => $name,
            'type' => $type,
            'format' => $format,
            'status' => 'pending',
        ]);

        GenerateProductReportJob::dispatch($report);

        return response()->json([
            'success' => true,
            'message' => 'Report generation job dispatched to queue.',
            'data' => $report,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Show report details.
     */
    public function show(Request $request, ProductReport $report): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        if (!$isAdmin && $businessUuid && $report->business_uuid !== $businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to report.',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Download generated report file.
     */
    public function download(Request $request, ProductReport $report): Response
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        if (!$isAdmin && $businessUuid && $report->business_uuid !== $businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to report.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($report->status !== 'completed' || empty($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report has not been completed yet.',
            ], Response::HTTP_NOT_FOUND);
        }

        $disk = config('filesystems.default', 'public');
        if (!Storage::disk($disk)->exists($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report file was not found in storage.',
            ], Response::HTTP_NOT_FOUND);
        }

        $contentType = $report->format === 'json' ? 'application/json' : 'text/csv';
        $fileContent = Storage::disk($disk)->get($report->file_path);
        $filename = basename($report->file_path);

        return response($fileContent, Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
