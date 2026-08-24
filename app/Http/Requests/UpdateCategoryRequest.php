<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'code')
                    ->where('business_uuid', $businessUuid)
                    ->ignore($categoryId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_uuid', $businessUuid),
            ],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->hasFile('image_path')) {
            $rules['image_path'] = ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:5120'];
        } elseif ($this->hasFile('image')) {
            $rules['image'] = ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:5120'];
        }

        return $rules;
    }
}
