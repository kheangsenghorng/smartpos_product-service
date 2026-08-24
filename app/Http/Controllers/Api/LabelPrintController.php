<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewLabelRequest;
use App\Http\Requests\PrintLabelRequest;
use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LabelPrintController extends Controller
{
    public function __construct(
        protected LabelService $labelService
    ) {}

    /**
     * Preview label format and data for a given product or variant.
     */
    public function preview(PreviewLabelRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $validated = $request->validated();

        $query = LabelTemplate::query();
        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        }
        $template = $query->findOrFail($validated['label_template_id']);

        $variant = null;
        if (!empty($validated['product_variant_id'])) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->findOrFail($validated['product_variant_id']);
        }

        $previewData = $this->labelService->previewLabel($product, $template, $variant);

        return response()->json([
            'success' => true,
            'data' => $previewData,
        ]);
    }

    /**
     * Dispatch and record a label print job for a product or variant.
     */
    public function print(PrintLabelRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $this->getBusinessUuid($request);
        $userUuid = $this->getUserUuid($request);

        $query = LabelTemplate::query();
        if ($businessUuid) {
            $query->where('business_uuid', $businessUuid);
        }
        $template = $query->findOrFail($request->input('label_template_id'));

        $variant = null;
        if ($request->filled('product_variant_id')) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->findOrFail($request->input('product_variant_id'));
        }

        $quantity = (int) $request->input('quantity', 1);

        $printLog = $this->labelService->recordPrintLog(
            $product,
            $template,
            $quantity,
            $variant,
            $userUuid
        );

        $previewData = $this->labelService->previewLabel($product, $template, $variant);

        return response()->json([
            'success' => true,
            'message' => "Label print job dispatched for {$quantity} item(s).",
            'data' => [
                'log' => $printLog,
                'preview' => $previewData,
            ],
        ], Response::HTTP_CREATED);
    }
}
