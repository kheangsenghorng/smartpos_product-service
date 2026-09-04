<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        // Handle boolean string values ("true", "false", "1", "0")
        if ($this->has('validate_only')) {
            $val = $this->input('validate_only');
            if ($val !== null && $val !== '') {
                $merge['validate_only'] = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if ($this->has('async')) {
            $val = $this->input('async');
            if ($val !== null && $val !== '') {
                $merge['async'] = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        // If a file is uploaded, remove csv_data completely so it never causes conflict
        if ($this->hasFile('file')) {
            $this->request->remove('csv_data');
            unset($this['csv_data']);
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx,xls,json', 'max:20480'],
            'csv_data' => ['nullable', 'string'],
            'validate_only' => ['nullable', 'boolean'],
            'async' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->hasFile('file') && empty($this->input('csv_data'))) {
                $v->errors()->add('file', 'Please choose a CSV/Excel file to upload or provide raw csv_data text.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'file.required_without' => 'Please choose a CSV or Excel file to upload, or provide raw csv_data text.',
            'csv_data.required_without' => 'Please provide raw csv_data text, or choose a CSV/Excel file to upload.',
            'file.mimes' => 'The uploaded file must be a CSV or Excel spreadsheet (.csv, .xlsx, .xls, .txt).',
            'file.max' => 'The uploaded file must not exceed 20MB in size.',
        ];
    }
}
