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
        $businessUuid = $this->attributes->get('auth_business_uuid') ?? $this->input('business_uuid');
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
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
            'image_path' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
