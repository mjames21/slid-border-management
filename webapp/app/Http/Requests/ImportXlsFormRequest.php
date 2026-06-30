<?php

namespace App\Http\Requests;

use App\Models\DynamicForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportXlsFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper((string) $this->input('country_code', 'SLE')),
            'reporting_module' => DynamicForm::normalizeModule($this->input('reporting_module')),
            'standard_reference' => $this->filled('standard_reference')
                ? trim((string) $this->input('standard_reference'))
                : DynamicForm::standardReferenceForModule($this->input('reporting_module')),
            'title' => $this->filled('title') ? trim((string) $this->input('title')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'mimes:xlsx,xls',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel',
            ],
            'country_code' => ['required', 'string', 'size:3', 'exists:countries,code'],
            'reporting_module' => ['required', 'string', Rule::in(array_keys(DynamicForm::moduleLabels()))],
            'standard_reference' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:120'],
            'publish' => ['nullable', 'boolean'],
        ];
    }
}
