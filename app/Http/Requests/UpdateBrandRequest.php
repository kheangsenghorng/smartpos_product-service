<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') ?? $this->input('business_uuid');
        $brand = $this->route('brand');
        $brandId = is_object($brand) ? $brand->id : $brand;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('brands', 'code')
                    ->where('business_uuid', $businessUuid)
                    ->ignore($brandId),
            ],
            'description' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
