<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\DynamicForm;
use App\Services\LocationOptionCatalog;

class BuildFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = collect($this->input('fields', []))
            ->filter(function (mixed $field): bool {
                if (!is_array($field)) {
                    return false;
                }

                return trim((string) ($field['id'] ?? '')) !== ''
                    || trim((string) ($field['label'] ?? '')) !== ''
                    || trim((string) ($field['hint'] ?? '')) !== ''
                    || trim((string) ($field['options'] ?? '')) !== '';
            })
            ->values()
            ->all();

        $this->merge([
            'country_code' => strtoupper((string) $this->input('country_code', 'SLE')),
            'reporting_module' => DynamicForm::normalizeModule($this->input('reporting_module')),
            'standard_reference' => $this->filled('standard_reference')
                ? trim((string) $this->input('standard_reference'))
                : DynamicForm::standardReferenceForModule($this->input('reporting_module')),
            'fields' => $fields,
        ]);
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'form_id' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'country_code' => ['required', 'string', 'size:3', 'exists:countries,code'],
            'reporting_module' => ['required', 'string', Rule::in(array_keys(DynamicForm::moduleLabels()))],
            'standard_reference' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'publish' => ['nullable', 'boolean'],
            'fields' => ['required', 'array', 'min:1', 'max:150'],
            'fields.*.include' => ['nullable', 'boolean'],
            'fields.*.id' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'fields.*.type' => ['required', Rule::in(['text', 'integer', 'decimal', 'date', 'datetime', 'select_one', 'select_multiple', 'note'])],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.hint' => ['nullable', 'string', 'max:500'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.option_source' => ['nullable', Rule::in(array_keys(app(LocationOptionCatalog::class)->sourceLabels()))],
            'fields.*.options' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $ids = [];

            foreach ($this->input('fields', []) as $index => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $id = strtolower(trim((string) ($field['id'] ?? '')));
                $id = preg_replace('/[^a-z0-9_]+/', '_', $id) ?? '';
                $id = trim($id, '_');
                if ($id === '') {
                    $id = strtolower(trim((string) ($field['label'] ?? '')));
                    $id = preg_replace('/[^a-z0-9_]+/', '_', $id) ?? '';
                    $id = trim($id, '_');
                }

                if ($id === '') {
                    continue;
                }

                if (in_array($id, $ids, true)) {
                    $validator->errors()->add("fields.{$index}.id", "The field id '{$id}' is already used on this form.");
                }

                $ids[] = $id;
            }
        });
    }
}
