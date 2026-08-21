<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabelTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'width_mm' => ['required', 'numeric', 'min:10', 'max:500'],
            'height_mm' => ['required', 'numeric', 'min:10', 'max:500'],
            'show_product_name' => ['nullable', 'boolean'],
            'show_variant_name' => ['nullable', 'boolean'],
            'show_price' => ['nullable', 'boolean'],
            'show_sku' => ['nullable', 'boolean'],
            'show_barcode' => ['nullable', 'boolean'],
            'show_qrcode' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
