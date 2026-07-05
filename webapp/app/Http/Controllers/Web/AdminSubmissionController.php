<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use App\Models\MobileSubmission;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminSubmissionController extends Controller
{
    use ResolvesTenantScope;

    public function index(Request $request): View
    {
        $submissions = $this->filteredQuery($request)->latest('received_at')->paginate(25)->withQueryString();

        return view('admin.submissions.index', [
            'submissions' => $submissions,
            'answerColumns' => $this->answerColumns($submissions->getCollection()),
            'filters' => array_merge($request->only(['country_code', 'reporting_module', 'form_id', 'device_id', 'status']), [
                'country_code' => $this->selectedCountryCode($request),
            ]),
            'countries' => $this->countriesForUser($request),
            'moduleLabels' => DynamicForm::moduleLabels(),
        ]);
    }

    public function show(MobileSubmission $submission): View
    {
        $this->assertCanAccessRecordCountry(request(), $submission);

        return view('admin.submissions.show', [
            'submission' => $submission,
            'answerRows' => $this->answerRows($submission),
        ]);
    }

    public function exportJson(Request $request, AuditLogger $audit): Response
    {
        $payload = $this->filteredQuery($request)->latest('received_at')->get();
        $audit->record('admin.submissions_exported', $request->user(), metadata: [
            'format' => 'json',
            'count' => $payload->count(),
            'filters' => $request->only(['country_code', 'reporting_module', 'form_id', 'device_id', 'status']),
        ], request: $request);

        return response(
            json_encode($payload, JSON_PRETTY_PRINT),
            200,
            $this->downloadHeaders('application/json', 'submissions.json')
        );
    }

    public function exportCsv(Request $request, AuditLogger $audit): Response
    {
        $rows = $this->filteredQuery($request)->latest('received_at')->get();
        $audit->record('admin.submissions_exported', $request->user(), metadata: [
            'format' => 'csv',
            'count' => $rows->count(),
            'filters' => $request->only(['country_code', 'reporting_module', 'form_id', 'device_id', 'status']),
        ], request: $request);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'id',
            'country_code',
            'border_post_code',
            'border_post_digital_address',
            'region',
            'reporting_module',
            'device_latitude',
            'device_longitude',
            'device_location_accuracy_meters',
            'device_location_captured_at',
            'form_id',
            'form_version',
            'device_id',
            'local_id',
            'status',
            'client_created_at',
            'client_updated_at',
            'received_at',
            'answers',
        ]);

        foreach ($rows as $submission) {
            fputcsv($handle, [
                $submission->id,
                $this->csvCell($submission->country_code),
                $this->csvCell($submission->border_post_code),
                $this->csvCell($submission->border_post_digital_address),
                $this->csvCell($submission->region),
                $this->csvCell($submission->reporting_module),
                $submission->device_latitude,
                $submission->device_longitude,
                $submission->device_location_accuracy_meters,
                $submission->device_location_captured_at,
                $this->csvCell($submission->form_id),
                $submission->form_version,
                $this->csvCell($submission->device_id),
                $this->csvCell($submission->local_id),
                $this->csvCell($submission->status),
                $submission->client_created_at,
                $submission->client_updated_at,
                $submission->received_at,
                $this->csvCell(json_encode($submission->answers)),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, $this->downloadHeaders('text/csv', 'submissions.csv'));
    }

    private function filteredQuery(Request $request): Builder
    {
        return MobileSubmission::query()
            ->when($this->selectedCountryCode($request), fn (Builder $query, string $countryCode) => $query->where('country_code', $countryCode))
            ->when($request->filled('reporting_module'), fn (Builder $query) => $query->where('reporting_module', DynamicForm::normalizeModule($request->string('reporting_module')->toString())))
            ->when($request->filled('form_id'), fn (Builder $query) => $query->where('form_id', $request->string('form_id')->toString()))
            ->when($request->filled('device_id'), fn (Builder $query) => $query->where('device_id', $request->string('device_id')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()));
    }

    private function answerRows(MobileSubmission $submission): array
    {
        $answers = is_array($submission->answers) ? $submission->answers : [];
        $version = $this->formVersionFor($submission);
        $fields = collect($version?->compiled_schema['fields'] ?? [])
            ->filter(fn (array $field) => !empty($field['id']))
            ->keyBy(fn (array $field) => (string) $field['id']);

        $rows = [];

        foreach ($fields as $fieldId => $field) {
            if (!array_key_exists($fieldId, $answers)) {
                continue;
            }

            $rows[] = $this->answerRow((string) $fieldId, $answers[$fieldId], $field, true);
        }

        foreach ($answers as $fieldId => $value) {
            if ($fields->has((string) $fieldId)) {
                continue;
            }

            $rows[] = $this->answerRow((string) $fieldId, $value, null, false);
        }

        return $rows;
    }

    private function answerColumns(iterable $submissions): array
    {
        $columns = [];

        foreach ($submissions as $submission) {
            $answers = is_array($submission->answers) ? $submission->answers : [];
            $version = $this->formVersionFor($submission);
            $fields = collect($version?->compiled_schema['fields'] ?? [])
                ->filter(fn (array $field) => !empty($field['id']))
                ->keyBy(fn (array $field) => (string) $field['id']);

            foreach (array_keys($answers) as $fieldId) {
                $fieldId = (string) $fieldId;
                $columns[$fieldId] ??= [
                    'id' => $fieldId,
                    'label' => $fields->get($fieldId)['label']
                        ?? Str::of($fieldId)->replace(['_', '-'], ' ')->title()->toString(),
                ];

                if (count($columns) >= 8) {
                    break 2;
                }
            }
        }

        return array_values($columns);
    }

    private function formVersionFor(MobileSubmission $submission): ?DynamicFormVersion
    {
        $form = DynamicForm::query()
            ->where('country_code', $submission->country_code)
            ->where('form_id', $submission->form_id)
            ->first();

        if (!$form) {
            return null;
        }

        return $form->versions()->where('version', $submission->form_version)->first();
    }

    private function answerRow(string $fieldId, mixed $value, ?array $field, bool $fromSchema): array
    {
        return [
            'field_id' => $fieldId,
            'label' => $field['label'] ?? Str::of($fieldId)->replace(['_', '-'], ' ')->title()->toString(),
            'type' => $field['type'] ?? 'unknown',
            'value' => $this->displayAnswerValue($value, $field),
            'from_schema' => $fromSchema,
        ];
    }

    private function displayAnswerValue(mixed $value, ?array $field): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $options = collect($field['options'] ?? [])->mapWithKeys(
            fn (array $option) => [(string) ($option['value'] ?? '') => (string) ($option['label'] ?? $option['value'] ?? '')]
        );

        if (is_array($value)) {
            $values = array_values($value);

            return collect($values)
                ->map(fn ($item) => $options->get((string) $item, $this->scalarAnswerValue($item)))
                ->implode(', ');
        }

        return $options->get((string) $value, $this->scalarAnswerValue($value));
    }

    private function scalarAnswerValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function downloadHeaders(string $contentType, string $filename): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ];
    }
}
