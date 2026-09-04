<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProductsRequest;
use App\Jobs\ImportProductsJob;
use App\Models\ProductReport;
use App\Services\ProductImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProductImportExportController extends Controller
{
    public function __construct(
        protected ProductImportExportService $importExportService
    ) {}

    /**
     * Import products from CSV or Excel (synchronous or background queue job).
     */
    public function import(ImportProductsRequest $request): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $userUuid = $this->getUserUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validateOnly = $request->boolean('validate_only');
        $isAsync = $request->boolean('async');

        // Handle uploaded file or inline CSV text
        $target = $request->hasFile('file') ? $request->file('file') : (string) $request->input('csv_data');

        // Dry-run validation is always synchronous so user sees results immediately
        if ($validateOnly) {
            $result = $this->importExportService->import(
                fileOrContent: $target,
                businessUuid: $businessUuid,
                userUuid: $userUuid,
                validateOnly: true
            );

            return response()->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Asynchronous queue execution for large uploads
        if ($isAsync) {
            $disk = config('filesystems.default', 'public');
            $ext = $request->hasFile('file') ? ($request->file('file')->getClientOriginalExtension() ?: 'csv') : 'csv';
            $tempPath = "temp_imports/" . Str::uuid() . "." . $ext;
            $rawContent = $request->hasFile('file') 
                ? file_get_contents($request->file('file')->getRealPath())
                : (string) $request->input('csv_data');
            Storage::disk($disk)->put($tempPath, $rawContent);

            $report = ProductReport::create([
                'business_uuid' => $businessUuid,
                'user_uuid' => $userUuid,
                'name' => 'Catalog CSV Import Job',
                'type' => 'import_log',
                'format' => $ext === 'xlsx' ? 'xlsx' : 'csv',
                'status' => 'pending',
                'metadata' => [
                    'filename' => $request->file('file')?->getClientOriginalName() ?? 'direct_upload.csv',
                ],
            ]);

            ImportProductsJob::dispatch($tempPath, $businessUuid, $userUuid, $report);

            return response()->json([
                'success' => true,
                'message' => 'Product import job dispatched to queue.',
                'data' => [
                    'job_id' => $report->uuid,
                    'status' => 'pending',
                    'check_status_url' => url("/api/v1/products/reports"),
                ],
            ], Response::HTTP_ACCEPTED);
        }

        // Synchronous import
        $result = $this->importExportService->import(
            fileOrContent: $target,
            businessUuid: $businessUuid,
            userUuid: $userUuid,
            validateOnly: false
        );

        $status = $result['success'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;

        return response()->json($result, $status);
    }

    /**
     * Export products to downloadable CSV stream.
     */
    public function export(Request $request): Response
    {
        $businessUuid = $this->getBusinessUuid($request);
        $isAdmin = $this->isGlobalAdmin($request);

        if (!$businessUuid && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $format = strtolower($request->input('format', 'csv'));
        $filters = $request->only(['category_id', 'brand_id', 'is_active']);

        if ($format === 'xlsx' || $format === 'excel') {
            $content = $this->importExportService->exportXlsx($businessUuid ?? '', $filters);
            $filename = 'products_export_' . date('Y-m-d_His') . '.xlsx';
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif ($format === 'json') {
            $content = $this->importExportService->exportJson($businessUuid ?? '', $filters);
            $filename = 'products_export_' . date('Y-m-d_His') . '.json';
            $mime = 'application/json';
        } else {
            $content = $this->importExportService->export($businessUuid ?? '', $filters);
            $filename = 'products_export_' . date('Y-m-d_His') . '.csv';
            $mime = 'text/csv; charset=UTF-8';
        }

        return response($content, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
