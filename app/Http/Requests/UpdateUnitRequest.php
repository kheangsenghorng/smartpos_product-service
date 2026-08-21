<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessUuid = $this->attributes->get('auth_business_uuid') ?? $this->input('business_uuid');
        $unit = $this->route('unit');
        $unitId = is_object($unit) ? $unit->id : $unit;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'code')
                    ->where('business_uuid', $businessUuid)
                    ->ignore($unitId),
            ],
            'symbol' => ['sometimes', 'required', 'string', 'max:20'],
            'precision' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
