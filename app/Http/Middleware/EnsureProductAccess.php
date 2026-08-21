<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\Unit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessUuid = $request->attributes->get('auth_business_uuid');

        if (!$businessUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate route parameters against business_uuid
        $parameters = [
            'category' => Category::class,
            'brand' => Brand::class,
            'unit' => Unit::class,
            'product' => Product::class,
            'variant' => ProductVariant::class,
            'code' => ProductCode::class,
            'price' => ProductPrice::class,
            'image' => ProductImage::class,
            'template' => LabelTemplate::class,
        ];

        foreach ($parameters as $param => $modelClass) {
            $value = $request->route($param);
            if ($value) {
                $model = $value instanceof $modelClass
                    ? $value
                    : $modelClass::where('id', $value)
                        ->orWhere('uuid', $value)
                        ->first();

                if ($model && $model->business_uuid !== $businessUuid) {
                    return response()->json([
                        'success' => false,
                        'message' => "Access denied to requested {$param} resource.",
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }
}
