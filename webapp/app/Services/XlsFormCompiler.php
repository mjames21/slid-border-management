<?php

namespace App\Services;

use App\Models\DynamicForm;
use PhpOffice\PhpSpreadsheet\IOFactory;

class XlsFormCompiler
{
    private const SUPPORTED_TYPES = ['text', 'integer', 'decimal', 'date', 'datetime', 'select_one', 'select_multiple', 'note', 'calculate'];
    private const METADATA_TYPES = ['start', 'end', 'today', 'deviceid', 'username'];
    private const LAYOUT_TYPES = ['begin_group', 'end_group'];

    public function compile(string $path, ?string $title = null, int $version = 1, string $reportingModule = 'immigration', ?string $standardReference = null): array
    {
        $reportingModule = DynamicForm::normalizeModule($reportingModule);
        $standardReference = trim((string) $standardReference) ?: DynamicForm::standardReferenceForModule($reportingModule);
        $spreadsheet = IOFactory::load($path);
        $survey = $this->sheetRows($spreadsheet, 'survey');
        $choices = $this->sheetRows($spreadsheet, 'choices');
        $settings = $this->sheetRows($spreadsheet, 'settings');
        $warnings = [];
        $ignoredRows = [];
        $unsupportedRows = [];

        $formId = $settings[0]['form_id'] ?? $settings[0]['id_string'] ?? pathinfo($path, PATHINFO_FILENAME);
        $formTitle = $title ?: ($settings[0]['form_title'] ?? $settings[0]['title'] ?? $formId);
        $defaultLanguage = $settings[0]['default_language'] ?? 'English';
        $choiceLists = $this->compileChoiceLists($choices);

        $fields = [];
        foreach ($survey as $rowNumber => $row) {
            $rawType = trim((string) ($row['type'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($rawType === '') {
                continue;
            }

            [$type, $listName] = $this->parseType($rawType);
            $excelRow = $rowNumber + 2;

            if (in_array($type, self::METADATA_TYPES, true)) {
                $ignoredRows[] = [
                    'row' => $excelRow,
                    'type' => $rawType,
                    'name' => $name,
                    'reason' => 'XLSForm metadata. The mobile app records its own local timestamps/device context during sync.',
                ];
                continue;
            }

            if (in_array($type, self::LAYOUT_TYPES, true)) {
                $ignoredRows[] = [
                    'row' => $excelRow,
                    'type' => $rawType,
                    'name' => $name,
                    'reason' => 'Layout/group marker. It organizes the XLSForm but is not a question sent to the mobile renderer.',
                ];
                continue;
            }

            if ($name === '') {
                $ignoredRows[] = [
                    'row' => $excelRow,
                    'type' => $rawType,
                    'name' => $name,
                    'reason' => 'No field name was provided, so there is no answer key to store.',
                ];
                continue;
            }

            if (!in_array($type, self::SUPPORTED_TYPES, true)) {
                $unsupportedRows[] = [
                    'row' => $excelRow,
                    'type' => $rawType,
                    'name' => $name,
                    'reason' => 'Not in the current FSD-supported mobile field subset.',
                ];
                continue;
            }

            $field = [
                'id' => $name,
                'type' => $type,
                'label' => $this->firstFilled($row, ['label', 'label::English', 'label:English']) ?: $name,
                'hint' => $this->firstFilled($row, ['hint', 'hint::English', 'hint:English']),
                'required' => $this->truthy($row['required'] ?? null),
            ];

            if ($listName !== null) {
                $field['listName'] = $listName;
                $field['options'] = $choiceLists[$listName] ?? [];
                if (!array_key_exists($listName, $choiceLists)) {
                    $warnings[] = "Row ".($rowNumber + 2).": choice list '{$listName}' was not found.";
                }
            }

            if (!empty($row['relevant'])) {
                $field['relevant'] = $this->compileRelevant((string) $row['relevant'], $warnings, $rowNumber + 2);
            }

            if ($type === 'calculate') {
                $field['calculation'] = $this->compileCalculation((string) ($row['calculation'] ?? ''), $warnings, $rowNumber + 2);
            }

            $fields[] = array_filter($field, static fn ($value) => $value !== null && $value !== '');
        }

        return [
            'schema' => [
                'formId' => $formId,
                'version' => $version,
                'title' => $formTitle,
                'reportingModule' => $reportingModule,
                'standardReference' => $standardReference,
                'defaultLanguage' => $defaultLanguage,
                'fields' => $fields,
                'choiceLists' => $choiceLists,
            ],
            'metadata' => [
                'warnings' => $warnings,
                'ignoredRows' => $ignoredRows,
                'unsupportedRows' => $unsupportedRows,
                'fieldCount' => count($fields),
                'choiceListCount' => count($choiceLists),
                'standardReference' => $standardReference,
                'compiled_at' => now()->toISOString(),
            ],
        ];
    }

    private function sheetRows($spreadsheet, string $name): array
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if (!$sheet) {
            return [];
        }

        $rows = $sheet->toArray(null, true, true, true);
        $headers = array_map(static fn ($value) => trim((string) $value), array_shift($rows) ?: []);
        $mapped = [];

        foreach ($rows as $row) {
            $item = [];
            foreach ($headers as $column => $header) {
                if ($header !== '') {
                    $item[$header] = is_string($row[$column] ?? null) ? trim($row[$column]) : ($row[$column] ?? null);
                }
            }

            if (array_filter($item, static fn ($value) => $value !== null && $value !== '')) {
                $mapped[] = $item;
            }
        }

        return $mapped;
    }

    private function compileChoiceLists(array $choices): array
    {
        $lists = [];
        foreach ($choices as $choice) {
            $listName = trim((string) ($choice['list_name'] ?? ''));
            $name = trim((string) ($choice['name'] ?? ''));
            if ($listName === '' || $name === '') {
                continue;
            }

            $lists[$listName][] = [
                'value' => $name,
                'label' => $this->firstFilled($choice, ['label', 'label::English', 'label:English']) ?: $name,
            ];
        }

        return $lists;
    }

    private function parseType(string $rawType): array
    {
        $parts = preg_split('/\s+/', $rawType, 2);
        return [$parts[0] ?? $rawType, $parts[1] ?? null];
    }

    private function firstFilled(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['yes', 'true', '1', 'required'], true);
    }

    private function compileRelevant(string $expression, array &$warnings, int $rowNumber): ?array
    {
        if (preg_match("/^\\$\\{([^}]+)}\\s*=\\s*'([^']*)'$/", trim($expression), $matches)) {
            return ['fieldId' => $matches[1], 'operator' => 'equals', 'value' => $matches[2]];
        }

        if (preg_match("/^\\$\\{([^}]+)}\\s*!=\\s*'([^']*)'$/", trim($expression), $matches)) {
            return ['fieldId' => $matches[1], 'operator' => 'not_equals', 'value' => $matches[2]];
        }

        $warnings[] = "Row {$rowNumber}: relevant expression '{$expression}' is not supported by the mobile renderer.";
        return null;
    }

    private function compileCalculation(string $expression, array &$warnings, int $rowNumber): array
    {
        $expression = trim($expression);
        if (preg_match("/^'([^']*)'$/", $expression, $matches)) {
            return ['kind' => 'constant', 'value' => $matches[1]];
        }

        if (preg_match('/^\\$\\{([^}]+)}$/', $expression, $matches)) {
            return ['kind' => 'copy', 'sourceFieldId' => $matches[1]];
        }

        if (str_contains($expression, '${')) {
            return ['kind' => 'template', 'template' => $expression];
        }

        $warnings[] = "Row {$rowNumber}: calculation '{$expression}' was treated as a constant.";
        return ['kind' => 'constant', 'value' => $expression];
    }
}
