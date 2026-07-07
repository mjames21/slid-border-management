<?php

namespace App\Services;

use App\Models\DynamicForm;

class FormBuilderCompiler
{
    private const FIELD_TYPES = ['text', 'integer', 'decimal', 'date', 'datetime', 'select_one', 'select_multiple', 'note'];

    public function __construct(private readonly LocationOptionCatalog $locationOptions)
    {
    }

    public function compile(array $input, int $version): array
    {
        $warnings = [];
        $fields = [];
        $choiceLists = [];

        foreach ($input['fields'] ?? [] as $index => $row) {
            if (array_key_exists('include', $row) && empty($row['include'])) {
                continue;
            }

            $id = $this->slug((string) ($row['id'] ?? ''));
            $type = (string) ($row['type'] ?? 'text');
            $label = trim((string) ($row['label'] ?? ''));

            if ($id === '' && $label === '') {
                continue;
            }

            if ($id === '') {
                $id = $this->slug($label);
            }

            if (!in_array($type, self::FIELD_TYPES, true)) {
                $warnings[] = "Field {$id}: unsupported type '{$type}' was changed to text.";
                $type = 'text';
            }

            $field = [
                'id' => $id,
                'type' => $type,
                'label' => $label !== '' ? $label : $id,
                'required' => !empty($row['required']),
            ];

            $hint = trim((string) ($row['hint'] ?? ''));
            if ($hint !== '') {
                $field['hint'] = $hint;
            }

            if (in_array($type, ['select_one', 'select_multiple'], true)) {
                $listName = $id.'_options';
                $field['listName'] = $listName;
                $optionSource = (string) ($row['option_source'] ?? LocationOptionCatalog::MANUAL_SOURCE);

                if ($optionSource !== LocationOptionCatalog::MANUAL_SOURCE && $this->locationOptions->isSupportedSource($optionSource)) {
                    $field['optionSource'] = $optionSource;
                    $options = $this->locationOptions->optionsFor($optionSource);
                    if (!$options) {
                        $warnings[] = "Field {$id}: option catalog '{$optionSource}' has no active options yet.";
                    }
                } else {
                    $options = $this->parseOptions((string) ($row['options'] ?? ''), $warnings, $id);
                }

                $field['options'] = $options;
                $choiceLists[$listName] = $options;
            }

            $fields[] = $field;
        }

        return [
            'schema' => [
                'formId' => $this->normalizeFormId((string) $input['form_id']),
                'version' => $version,
                'title' => trim((string) $input['title']),
                'reportingModule' => DynamicForm::normalizeModule($input['reporting_module'] ?? null),
                'standardReference' => trim((string) ($input['standard_reference'] ?? '')) ?: DynamicForm::standardReferenceForModule($input['reporting_module'] ?? null),
                'defaultLanguage' => 'English',
                'fields' => $fields,
                'choiceLists' => $choiceLists,
            ],
            'metadata' => [
                'source' => 'form_builder',
                'builderRows' => array_values($input['fields'] ?? []),
                'warnings' => $warnings,
                'ignoredRows' => [],
                'unsupportedRows' => [],
                'fieldCount' => count($fields),
                'choiceListCount' => count($choiceLists),
                'standardReference' => trim((string) ($input['standard_reference'] ?? '')) ?: DynamicForm::standardReferenceForModule($input['reporting_module'] ?? null),
                'compiled_at' => now()->toISOString(),
            ],
        ];
    }

    public function builderRowsFromSchema(array $schema): array
    {
        return collect($schema['fields'] ?? [])
            ->reject(fn (array $field) => ($field['type'] ?? null) === 'calculate')
            ->map(function (array $field): array {
                $options = collect($field['options'] ?? [])
                    ->map(fn (array $option) => ($option['value'] ?? '').'|'.($option['label'] ?? $option['value'] ?? ''))
                    ->implode("\n");

                return [
                    'include' => true,
                    'id' => $field['id'] ?? '',
                    'type' => $field['type'] ?? 'text',
                    'label' => $field['label'] ?? '',
                    'hint' => $field['hint'] ?? '',
                    'required' => !empty($field['required']),
                    'option_source' => $field['optionSource'] ?? LocationOptionCatalog::MANUAL_SOURCE,
                    'options' => $options,
                ];
            })
            ->values()
            ->all();
    }

    public function fieldTypes(): array
    {
        return self::FIELD_TYPES;
    }

    private function parseOptions(string $raw, array &$warnings, string $fieldId): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$value, $label] = array_pad(explode('|', $line, 2), 2, null);
            $value = $this->slug((string) $value);
            $label = trim((string) ($label ?? $value));

            if ($value === '') {
                $warnings[] = "Field {$fieldId}: an option was skipped because it had no value.";
                continue;
            }

            $options[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
        }

        if (!$options) {
            $warnings[] = "Field {$fieldId}: select field has no options.";
        }

        return $options;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value;
    }

    private function normalizeFormId(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '_', $value) ?? '';
        $value = trim($value, '._-');

        return $value;
    }
}
