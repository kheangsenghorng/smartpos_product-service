<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') ?? $this->input('business_uuid');
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? $variant->id : $variant;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')
                    ->where('business_uuid', $businessUuid)
                    ->ignore($variantId),
            ],
            'sort_order' => ['nullable', 'integer'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
