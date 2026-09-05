<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        $rules = [
            'product_variant_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variants', 'id')->where('product_id', $productId),
            ],
            'image' => ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'disk' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
            'is_primary' => ['nullable', 'boolean'],
        ];

        if ($this->hasFile('image_path')) {
            $rules['image_path'] = ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:10240'];
        } elseif (!$this->hasFile('image')) {
            $rules['image_path'] = ['required', 'string', 'max:500'];
        } else {
            $rules['image_path'] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }
}
