<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
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
                Rule::unique('brands', 'code')->where('business_uuid', $businessUuid),
            ],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'file', 'image', 'mimes:webp,png,jpg,jpeg,svg,gif,bmp,avif', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (!$businessUuid) {
            $rules['business_uuid'] = ['required', 'string'];
        }

        return $rules;
    }
}
