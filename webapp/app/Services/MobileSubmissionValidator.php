<?php

namespace App\Services;

use App\Models\DynamicFormVersion;
use Illuminate\Support\Str;

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

    public function normalizeAnswers(DynamicFormVersion $version, array $answers): array
    {
        $schema = $version->compiled_schema;

        foreach ($schema['fields'] ?? [] as $field) {
            $id = $field['id'] ?? null;
            if (! $id || ! array_key_exists($id, $answers) || ! in_array($field['type'] ?? null, ['select_one', 'select_multiple'], true)) {
                continue;
            }

            $original = $answers[$id];
            $values = is_array($original) ? $original : [$original];
            $normalized = [];

            foreach ($values as $value) {
                $value = (string) $value;
                $normalized[] = $this->normalizeChoice($field, $value, $schema) ?? $value;
            }

            $answers[$id] = is_array($original) ? $normalized : ($normalized[0] ?? $original);
        }

        return $answers;
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
        if ($this->normalizeChoice($field, $value, $schema) !== null) {
            return [];
        }

        return [($field['label'] ?? $field['id'] ?? 'field').' has an invalid choice.'];
    }

    private function normalizeChoice(array $field, string $value, array $schema): ?string
    {
        $choices = $this->choicesFor($field, $schema);
        $value = trim($value);

        foreach ($choices as $choice) {
            $choiceValue = (string) ($choice['value'] ?? '');
            if ($choiceValue === $value) {
                return $choiceValue;
            }
        }

        foreach ($choices as $choice) {
            $choiceValue = (string) ($choice['value'] ?? '');
            $choiceLabel = (string) ($choice['label'] ?? '');

            if (strcasecmp($choiceValue, $value) === 0 || strcasecmp($choiceLabel, $value) === 0) {
                return $choiceValue;
            }
        }

        if (! $this->isLocationChoice($field)) {
            return null;
        }

        $fingerprint = $this->choiceFingerprint($value);
        foreach ($choices as $choice) {
            $choiceValue = (string) ($choice['value'] ?? '');
            $choiceLabel = (string) ($choice['label'] ?? '');

            if (
                $fingerprint !== ''
                && ($fingerprint === $this->choiceFingerprint($choiceValue) || $fingerprint === $this->choiceFingerprint($choiceLabel))
            ) {
                return $choiceValue;
            }
        }

        return null;
    }

    private function choicesFor(array $field, array $schema): array
    {
        $listName = $field['listName'] ?? null;

        return $field['options'] ?? ($schema['choiceLists'][$listName] ?? []);
    }

    private function isLocationChoice(array $field): bool
    {
        $id = (string) ($field['id'] ?? '');
        $optionSource = (string) ($field['optionSource'] ?? $field['option_source'] ?? '');

        return str_starts_with($optionSource, 'locations:')
            || in_array($id, ['origin_location', 'destination_location'], true)
            || Str::endsWith($id, '_location');
    }

    private function choiceFingerprint(string $value): string
    {
        return Str::lower((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));
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
