<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') ?? $this->input('business_uuid');
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $productId),
            ],
            'code_type' => ['required', 'string', 'in:barcode,qrcode'],
            'symbology' => ['nullable', 'string', 'max:30'],
            'code_value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_codes', 'code_value')->where('business_uuid', $businessUuid),
            ],
            'image_path' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['nullable', 'boolean'],
            'is_auto_generated' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
