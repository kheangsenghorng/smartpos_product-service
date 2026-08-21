<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintLabelRequest;
use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LabelPrintController extends Controller
{
    public function __construct(
        protected LabelService $labelService
    ) {}

    public function preview(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'label_template_id' => ['required', 'integer', 'exists:label_templates,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        $template = LabelTemplate::findOrFail($validated['label_template_id']);

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

    public function print(PrintLabelRequest $request, Product $product): JsonResponse
    {
        $businessUuid = $request->attributes->get('auth_business_uuid') ?? $request->input('business_uuid');
        $userUuid = $request->attributes->get('auth_user_uuid');

        $template = LabelTemplate::where('business_uuid', $businessUuid)
            ->findOrFail($request->input('label_template_id'));

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
