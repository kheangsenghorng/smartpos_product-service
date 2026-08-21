<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where('business_uuid', $businessUuid)
                    ->ignore($productId),
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
                'sometimes',
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
        ];
    }
}
