<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') ?? $this->input('business_uuid');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->where('business_uuid', $businessUuid),
            ],
            'sort_order' => ['nullable', 'integer'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],

            // Optional initial price and code options
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'code_option' => ['nullable', 'string', 'in:none,barcode,qrcode,both'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'qrcode' => ['nullable', 'string', 'max:255'],
        ];
    }
}
