<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
                Rule::unique('products', 'sku')->where('business_uuid', $businessUuid),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_uuid', $businessUuid),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('business_uuid', $businessUuid),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where('business_uuid', $businessUuid),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'track_inventory' => ['nullable', 'boolean'],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],

            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],

            // Code creation option
            'code_option' => ['nullable', 'string', 'in:none,barcode,qrcode,both'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'qrcode' => ['nullable', 'string', 'max:255'],

            // Initial price option
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],

            // Variants array option
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.sort_order' => ['nullable', 'integer'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.code_option' => ['nullable', 'string', 'in:none,barcode,qrcode,both'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.qrcode' => ['nullable', 'string', 'max:255'],

            // Initial images
            'images' => ['nullable', 'array'],
        ];
    }
}
