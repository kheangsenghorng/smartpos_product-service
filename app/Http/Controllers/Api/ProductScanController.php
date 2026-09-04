<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductScanController extends Controller
{
    public function __construct(
        protected ProductScanService $scanService
    ) {}

    /**
     * High-speed barcode/SKU scanner lookup for POS checkouts.
     */
    public function scan(Request $request, string $code): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context (business_uuid) is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->scanService->scan($code, $businessUuid);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => "No product or variant found matching code: {$code}",
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item resolved successfully.',
            'data' => $result,
        ]);
    }
}
