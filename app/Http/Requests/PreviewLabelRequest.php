<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') 
            ?? $this->header('X-Business-Uuid') 
            ?? $this->input('business_uuid');
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return [
            'label_template_id' => [
                'required',
                'integer',
                $businessUuid
                    ? Rule::exists('label_templates', 'id')->where('business_uuid', $businessUuid)
                    : 'exists:label_templates,id',
            ],
            'product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $productId),
            ],
            'business_uuid' => ['required', 'string'],
        ];
    }
}
