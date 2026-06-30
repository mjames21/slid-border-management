<?php

namespace App\Services;

use App\Models\DynamicFormVersion;

class MobileSubmissionValidator
{
    public function validate(DynamicFormVersion $version, array $answers): array
    {
        $schema = $version->compiled_schema;
        $errors = [];

        foreach ($schema['fields'] ?? [] as $field) {
            if (($field['type'] ?? null) === 'calculate' || !$this->isVisible($field, $answers)) {
                continue;
            }

            $id = $field['id'] ?? null;
            if (!$id) {
                continue;
            }

            $values = $answers[$id] ?? [];
            $values = is_array($values) ? array_values(array_filter($values, static fn ($value) => trim((string) $value) !== '')) : [$values];

            if (($field['required'] ?? false) && count($values) === 0) {
                $errors[] = "{$id} is required.";
                continue;
            }

            foreach ($values as $value) {
                $errors = array_merge($errors, $this->validateValue($field, $value, $schema));
            }
        }

        return $errors;
    }

    private function validateValue(array $field, mixed $value, array $schema): array
    {
        $id = $field['id'] ?? 'field';
        $value = (string) $value;

        return match ($field['type'] ?? 'text') {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) === false ? ["{$id} must be an integer."] : [],
            'decimal' => filter_var($value, FILTER_VALIDATE_FLOAT) === false ? ["{$id} must be a decimal number."] : [],
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? [] : ["{$id} must be a YYYY-MM-DD date."],
            'datetime' => strtotime($value) === false ? ["{$id} must be a valid date/time."] : [],
            'select_one', 'select_multiple' => $this->validateChoice($field, $value, $schema),
            default => [],
        };
    }

    private function validateChoice(array $field, string $value, array $schema): array
    {
        $listName = $field['listName'] ?? null;
        $choices = $field['options'] ?? ($schema['choiceLists'][$listName] ?? []);
        $allowed = array_column($choices, 'value');

        return in_array($value, $allowed, true) ? [] : [($field['id'] ?? 'field')." has an invalid choice."];
    }

    private function isVisible(array $field, array $answers): bool
    {
        $rule = $field['relevant'] ?? null;
        if (!$rule) {
            return true;
        }

        $current = $answers[$rule['fieldId'] ?? ''] ?? [];
        $current = is_array($current) ? $current : [$current];
        $first = $current[0] ?? null;

        return match ($rule['operator'] ?? null) {
            'equals' => $first === ($rule['value'] ?? null),
            'not_equals' => $first !== ($rule['value'] ?? null),
            'not_empty' => count(array_filter($current)) > 0,
            'empty' => count(array_filter($current)) === 0,
            'in' => count(array_intersect($current, $rule['values'] ?? [])) > 0,
            default => true,
        };
    }
}
