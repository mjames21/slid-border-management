<?php

namespace App\Services;

use App\Models\BorderPost;
use App\Models\Country;
use App\Models\FrequentLocation;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LocationOptionCatalog
{
    public const MANUAL_SOURCE = 'manual';

    private const COUNTRY_NAMES = [
        'SLE' => 'Sierra Leone',
        'GIN' => 'Guinea',
        'LBR' => 'Liberia',
    ];

    private const CORRIDOR_COUNTRIES = [
        'SLE' => ['SLE', 'GIN', 'LBR'],
        'GIN' => ['GIN', 'SLE'],
        'LBR' => ['LBR', 'SLE'],
    ];

    public function sourceLabels(): array
    {
        return [
            self::MANUAL_SOURCE => 'Manual options',
            'countries:all' => 'Countries: all configured countries',
            'locations:all' => 'Frequent locations: Sierra Leone, Guinea, Liberia',
            'locations:SLE' => 'Frequent locations: Sierra Leone',
            'locations:GIN' => 'Frequent locations: Guinea',
            'locations:LBR' => 'Frequent locations: Liberia',
        ];
    }

    public function isSupportedSource(?string $source): bool
    {
        return array_key_exists($source ?: self::MANUAL_SOURCE, $this->sourceLabels());
    }

    public function optionsFor(?string $source, ?BorderPost $borderPost = null): array
    {
        $source = $source ?: self::MANUAL_SOURCE;
        if ($source === self::MANUAL_SOURCE) {
            return [];
        }

        if ($source === 'countries:all') {
            return $this->countryOptions();
        }

        $query = FrequentLocation::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('country_code')
            ->orderBy('name');

        $countryCode = $this->countryCodeFromSource($source);
        if ($countryCode) {
            $query->where('country_code', $countryCode);
        }

        $district = $this->districtFromBorderPost($borderPost);
        if ($district) {
            $query->where(function ($query) use ($district): void {
                $query
                    ->where('district', $district)
                    ->orWhere('admin_area', $district)
                    ->orWhere('admin_area', 'like', $this->likeValue($district.' /'))
                    ->orWhere('admin_area', 'like', $this->likeValue('/ '.$district));
            });
        }

        $options = $query->get()
            ->map(fn (FrequentLocation $location): array => [
                'value' => $this->optionValue($location),
                'label' => $this->optionLabel($location),
            ])
            ->values()
            ->all();

        if (str_starts_with($source, 'locations:')) {
            $options[] = $this->otherLocationOption();
        }

        return $options;
    }

    public function hydrateSchema(array $schema, ?BorderPost $borderPost = null): array
    {
        $choiceLists = $schema['choiceLists'] ?? [];
        $fields = collect($schema['fields'] ?? [])
            ->map(function (array $field) use (&$choiceLists, $borderPost): array {
                $source = $field['optionSource'] ?? null;
                if (!$source || (!in_array($source, ['countries:all'], true) && !str_starts_with($source, 'locations:'))) {
                    return $field;
                }

                $options = $this->optionsFor($source, $borderPost);
                $field['options'] = $options;

                $listName = $field['listName'] ?? $field['id'].'_options';
                $field['listName'] = $listName;
                $choiceLists[$listName] = $options;

                return $field;
            })
            ->values()
            ->all();

        $schema['fields'] = $fields;
        $schema['choiceLists'] = $choiceLists;

        return $schema;
    }

    public function importSpreadsheet(string $path, ?array $allowedCountryCodes = null): array
    {
        $allowedCountryCodes = $this->expandAllowedCountryCodes($allowedCountryCodes);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        if (!$rows) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['The upload did not contain any rows.']];
        }

        $headers = $this->headersFromRow(array_shift($rows) ?: []);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $rowNumber => $row) {
            $data = $this->mapRow($headers, $row);
            $countryCode = $this->normalizeCountryCode($data['country_code'] ?? $data['country'] ?? '');
            $name = trim((string) ($data['name'] ?? $data['location'] ?? $data['place'] ?? ''));
            $code = $this->normalizeLocationCode($data['code'] ?? $data['location_code'] ?? null);

            if ($countryCode === null || $name === '') {
                $skipped++;
                $errors[] = 'Row '.($rowNumber + 2).': country and location name are required.';
                continue;
            }

            if ($allowedCountryCodes !== null && !in_array($countryCode, $allowedCountryCodes, true)) {
                $skipped++;
                $errors[] = 'Row '.($rowNumber + 2).": {$countryCode} is outside your workspace.";
                continue;
            }

            $adminArea = $this->nullableString($data['admin_area'] ?? $data['district'] ?? $data['province'] ?? null);
            $attributes = $code
                ? ['code' => $code]
                : [
                    'country_code' => $countryCode,
                    'name' => $name,
                    'admin_area' => $adminArea,
                ];

            $location = FrequentLocation::query()->firstOrNew($attributes);
            $wasNew = !$location->exists;

            $location->fill([
                'code' => $code ?: $location->code ?: $this->makeLocationCode($countryCode, $name, $adminArea),
                'country_name' => self::COUNTRY_NAMES[$countryCode],
                'country_code' => $countryCode,
                'name' => $name,
                'admin_area' => $adminArea,
                'district' => $this->nullableString($data['district'] ?? null)
                    ?: $this->inferDistrict($data['admin_area'] ?? $data['area'] ?? null),
                'category' => $this->nullableString($data['category'] ?? $data['type'] ?? null),
                'aliases' => $this->nullableString($data['aliases'] ?? $data['alias'] ?? null),
                'is_active' => true,
                'sort_order' => (int) ($data['sort_order'] ?? $data['order'] ?? 0),
            ])->save();

            $wasNew ? $created++ : $updated++;
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    public function normalizeCountryCode(string $value): ?string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z]+/', ' ', $value) ?? '';
        $value = trim($value);

        return match ($value) {
            'SLE', 'SL', 'SIERRA LEONE' => 'SLE',
            'GIN', 'GN', 'GUINEA', 'GUINEA CONAKRY', 'GUINEE', 'GUINEE CONAKRY' => 'GIN',
            'LBR', 'LR', 'LIBERIA' => 'LBR',
            default => null,
        };
    }

    public function expandAllowedCountryCodes(?array $countryCodes): ?array
    {
        if ($countryCodes === null) {
            return null;
        }

        return collect($countryCodes)
            ->map(fn (mixed $countryCode): ?string => $this->normalizeCountryCode((string) $countryCode))
            ->filter()
            ->flatMap(fn (string $countryCode): array => self::CORRIDOR_COUNTRIES[$countryCode] ?? [$countryCode])
            ->unique()
            ->values()
            ->all();
    }

    private function countryCodeFromSource(string $source): ?string
    {
        if ($source === 'locations:all') {
            return null;
        }

        $candidate = str_replace('locations:', '', $source);

        return $this->normalizeCountryCode($candidate);
    }

    private function countryOptions(): array
    {
        return Country::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Country $country): array => [
                'value' => strtolower($country->code),
                'label' => $country->name,
            ])
            ->values()
            ->all();
    }

    private function otherLocationOption(): array
    {
        return [
            'value' => 'other',
            'label' => 'Other location',
        ];
    }

    private function optionValue(FrequentLocation $location): string
    {
        if ($location->code) {
            return $location->code;
        }

        $parts = array_filter([$location->country_code, $location->name, $location->admin_area]);
        $value = strtolower(implode('_', $parts));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function optionLabel(FrequentLocation $location): string
    {
        $area = $location->admin_area ? ', '.$location->admin_area : '';

        return "{$location->name}{$area} ({$location->country_code})";
    }

    private function districtFromBorderPost(?BorderPost $borderPost): ?string
    {
        if (!$borderPost) {
            return null;
        }

        $region = trim((string) $borderPost->region);
        if ($region !== '') {
            $district = trim(strtok($region, '/') ?: $region);
            if ($district !== '') {
                return $district;
            }
        }

        return $this->inferDistrict($borderPost->name);
    }

    private function inferDistrict(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $district = trim(strtok($value, '/') ?: $value);

        return $district === '' ? null : $district;
    }

    private function likeValue(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }

    private function headersFromRow(array $row): array
    {
        return collect($row)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->map(fn (string $value): string => preg_replace('/[^a-z0-9]+/', '_', $value) ?? '')
            ->map(fn (string $value): string => trim($value, '_'))
            ->all();
    }

    private function mapRow(array $headers, array $row): array
    {
        return collect($row)
            ->mapWithKeys(function ($value, string $column) use ($headers): array {
                $key = $headers[$column] ?? strtolower($column);

                return [$key => is_string($value) ? trim($value) : $value];
            })
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeLocationCode(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::upper(Str::slug($value, '-'));
    }

    private function makeLocationCode(string $countryCode, string $name, ?string $adminArea): string
    {
        return Str::upper(Str::slug(collect([$countryCode, $name, $adminArea])->filter()->implode('-'), '-'));
    }
}
