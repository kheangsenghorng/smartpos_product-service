<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('is_active')) {
            $merge['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($this->has('parent_id')) {
            $parentId = $this->input('parent_id');
            if ($parentId === '' || $parentId === 'null' || $parentId === 'undefined' || $parentId === 0 || $parentId === '0') {
                $merge['parent_id'] = null;
            } elseif (is_numeric($parentId)) {
                $merge['parent_id'] = (int) $parentId;
            }
        }

        if ($this->has('sort_order')) {
            $sortOrder = $this->input('sort_order');
            if ($sortOrder === '' || $sortOrder === 'null' || $sortOrder === 'undefined') {
                $merge['sort_order'] = 0;
            } elseif (is_numeric($sortOrder)) {
                $merge['sort_order'] = (int) $sortOrder;
            }
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') 
            ?? $this->header('X-Business-Uuid') 
            ?? $this->input('business_uuid');

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categories', 'code')->where('business_uuid', $businessUuid),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_uuid', $businessUuid),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:5120'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->hasFile('image_path')) {
            $rules['image_path'] = ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:5120'];
        }

        if (!$businessUuid) {
            $rules['business_uuid'] = ['required', 'string'];
        }

        return $rules;
    }
}
