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
        $roles = (array) $request->attributes->get('auth_roles', []);
        $payload = (array) $request->attributes->get('jwt_payload', []);
        
        $isGlobalAdmin = in_array('admin', $roles, true)
            || in_array('super_admin', $roles, true)
            || in_array('superadmin', $roles, true)
            || !empty($payload['is_admin']);

        $businessUuid = $request->attributes->get('auth_business_uuid') 
            ?? $request->header('X-Business-Uuid') 
            ?? $request->input('business_uuid');

        // Require business_uuid if not a global admin
        if (!$businessUuid && !$isGlobalAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Business context is required. Please provide business_uuid in the request body, X-Business-Uuid header, or JWT token.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($businessUuid) {
            $request->attributes->set('auth_business_uuid', $businessUuid);
            $request->attributes->set('business_uuid', $businessUuid);
            if (!$request->has('business_uuid')) {
                $request->merge(['business_uuid' => $businessUuid]);
            }
        }

        // Validate route parameters against business_uuid for tenant isolation
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
            if ($value && $businessUuid && !$isGlobalAdmin) {
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
