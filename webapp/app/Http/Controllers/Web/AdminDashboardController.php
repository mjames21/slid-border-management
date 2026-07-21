<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Models\BorderPost;
use App\Models\Country;
use App\Models\DashboardView;
use App\Models\DynamicForm;
use App\Models\MobileSubmission;
use App\Services\CountryBoundaryImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    use ResolvesTenantScope;

    private const DEFAULT_LAYOUT = ['map', 'timeline', 'breakdowns', 'quality', 'discover', 'devices', 'detail', 'reports', 'aggregates'];
    private const MAX_MAP_BOUNDARY_COORDINATES = 12000;

    public function index(Request $request): View
    {
        return view('admin.dashboard.index', [
            'countries' => $this->countriesForUser($request),
            'dashboardViews' => $this->serializedDashboardViews($request->user()->id),
            'filterFields' => $this->publicFilterFields(),
            'operatorLabels' => $this->operatorLabels(),
        ]);
    }

    public function data(Request $request, CountryBoundaryImporter $boundaries): JsonResponse
    {
        $countryCode = $this->defaultCountryCode($request);
        $country = $this->countryQueryForUser($request)->find($countryCode)
            ?: $this->countryQueryForUser($request)->first();
        $window = $this->resolveDashboardWindow($request->query('hours', 24));
        $hours = $window['hours'];
        $filters = $this->normalizeFilters($request->query('filters', []));
        $search = substr(trim((string) $request->query('q', '')), 0, 120);
        $mapOnly = $request->query('view') === 'map';

        $baseQuery = $this->filteredSubmissionQuery($country?->code, $window['from'], $filters, $window['to']);
        $this->applyDiscoverSearch($baseQuery, $search);
        $total = (clone $baseQuery)->count();
        $withLocation = (clone $baseQuery)->whereNotNull('device_latitude')->whereNotNull('device_longitude')->count();
        $analysisRows = collect();

        if (!$mapOnly) {
            $analysisRows = (clone $baseQuery)
                ->latest('received_at')
                ->limit(5000)
                ->get([
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
                    'answers',
                    'client_created_at',
                    'received_at',
                    'client_synced_at',
                    'rejection_reason',
                    'server_uid',
                ]);
        }

        $points = (clone $baseQuery)
            ->whereNotNull('device_latitude')
            ->whereNotNull('device_longitude')
            ->latest('received_at')
            ->limit(500)
            ->get()
            ->map(fn (MobileSubmission $submission) => $this->serializeSubmission($submission))
            ->values();

        $latestReports = $mapOnly
            ? $points->take(80)->values()
            : $analysisRows
                ->take(200)
                ->map(fn (MobileSubmission $submission) => $this->serializeSubmission($submission))
                ->values();

        [$mapBoundary, $boundaryCoordinateCount, $boundarySimplified] = $this->cachedMapBoundary($country, $boundaries);

        return response()->json([
            'generatedAt' => now()->toIso8601String(),
            'country' => [
                'code' => $country?->code,
                'name' => $country?->name,
                'appTitle' => $country?->app_title,
                'hasBoundary' => $country?->boundary_geojson_path !== null,
            ],
            'windowHours' => $hours,
            'window' => [
                'key' => $window['key'],
                'label' => $window['label'],
                'from' => $window['from']?->toIso8601String(),
                'to' => $window['to']?->toIso8601String(),
            ],
            'filters' => $filters,
            'query' => $search,
            'filterOptions' => $this->filterOptions($country),
            'boundary' => $mapBoundary,
            'boundaryMeta' => [
                'coordinateCount' => $boundaryCoordinateCount,
                'simplified' => $boundarySimplified,
                'maxCoordinates' => self::MAX_MAP_BOUNDARY_COORDINATES,
            ],
            'metrics' => [
                'total' => $total,
                'withLocation' => $withLocation,
                'withoutLocation' => max(0, $total - $withLocation),
                'gpsCoveragePercent' => $total > 0 ? round(($withLocation / $total) * 100, 1) : 0,
                'today' => $this->filteredSubmissionQuery($country?->code, today(), $filters)->count(),
                'lastHour' => $this->filteredSubmissionQuery($country?->code, now()->subHour(), $filters)->count(),
                'rejected' => (clone $baseQuery)->where('status', '!=', 'accepted')->count(),
                'uniqueDevices' => (clone $baseQuery)->whereNotNull('device_id')->distinct('device_id')->count('device_id'),
            ],
            'aggregates' => $mapOnly ? [] : [
                'byBorderPost' => $this->aggregate($baseQuery, 'border_post_code'),
                'byForm' => $this->aggregate($baseQuery, 'form_id'),
                'byRegion' => $this->aggregate($baseQuery, 'region'),
                'byModule' => $this->aggregateModules($baseQuery),
            ],
            'analysis' => $mapOnly ? [] : $this->analysisPayload($analysisRows, $hours),
            'points' => $points,
            'latestReports' => $latestReports,
        ]);
    }

    public function storeView(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'country_code' => ['required', 'string', 'size:3', 'exists:countries,code'],
            'time_window_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'filters' => ['nullable', 'array', 'max:10'],
            'layout' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $this->assertCanAccessCountry($request, $validated['country_code']);

        $userId = $request->user()->id;
        $name = trim($validated['name']);
        $view = isset($validated['id'])
            ? DashboardView::query()->where('user_id', $userId)->findOrFail($validated['id'])
            : DashboardView::query()->firstOrNew(['user_id' => $userId, 'name' => $name]);

        if ($request->boolean('is_default')) {
            DashboardView::query()->where('user_id', $userId)->update(['is_default' => false]);
        }

        $view->fill([
            'user_id' => $userId,
            'country_code' => strtoupper($validated['country_code']),
            'name' => $name,
            'description' => $validated['description'] ?? null,
            'time_window_hours' => (int) $validated['time_window_hours'],
            'filters' => $this->normalizeFilters($validated['filters'] ?? []),
            'layout' => $this->normalizeLayout($validated['layout'] ?? []),
            'is_default' => $request->boolean('is_default'),
        ])->save();

        return response()->json([
            'view' => $this->serializeDashboardView($view->fresh()),
            'views' => $this->serializedDashboardViews($userId),
        ]);
    }

    public function destroyView(Request $request, DashboardView $dashboardView): JsonResponse
    {
        abort_unless($dashboardView->user_id === $request->user()->id, 403);

        $dashboardView->delete();

        return response()->json([
            'views' => $this->serializedDashboardViews($request->user()->id),
        ]);
    }

    private function resolveDashboardWindow(mixed $rawWindow): array
    {
        $key = is_string($rawWindow) ? trim($rawWindow) : (string) $rawWindow;
        $now = now();

        $window = match ($key) {
            'this_month' => [
                'key' => 'this_month',
                'label' => 'This month',
                'from' => $now->copy()->startOfMonth(),
                'to' => null,
            ],
            'last_month' => [
                'key' => 'last_month',
                'label' => 'Last month',
                'from' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'to' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_3_months' => [
                'key' => 'last_3_months',
                'label' => 'Last 3 months',
                'from' => $now->copy()->subMonthsNoOverflow(3),
                'to' => null,
            ],
            'last_6_months' => [
                'key' => 'last_6_months',
                'label' => 'Last 6 months',
                'from' => $now->copy()->subMonthsNoOverflow(6),
                'to' => null,
            ],
            'this_year' => [
                'key' => 'this_year',
                'label' => 'This year',
                'from' => $now->copy()->startOfYear(),
                'to' => null,
            ],
            'last_year' => [
                'key' => 'last_year',
                'label' => 'Last year',
                'from' => $now->copy()->subYearNoOverflow()->startOfYear(),
                'to' => $now->copy()->subYearNoOverflow()->endOfYear(),
            ],
            'all' => [
                'key' => 'all',
                'label' => 'All records',
                'from' => null,
                'to' => null,
            ],
            default => null,
        };

        if ($window === null) {
            $numericHours = (int) $key;
            $hours = max(1, min(8760, $numericHours > 0 ? $numericHours : 24));

            return [
                'key' => (string) $hours,
                'label' => $this->hourWindowLabel($hours),
                'from' => $now->copy()->subHours($hours),
                'to' => null,
                'hours' => $hours,
            ];
        }

        $window['hours'] = $window['from']
            ? max(1, (int) ceil(abs($window['from']->diffInMinutes($window['to'] ?? $now)) / 60))
            : 8760;

        return $window;
    }

    private function hourWindowLabel(int $hours): string
    {
        return match ($hours) {
            1 => 'Last hour',
            24 => 'Last 24 hours',
            72 => 'Last 3 days',
            168 => 'Last 7 days',
            default => "Last {$hours} hours",
        };
    }

    private function filteredSubmissionQuery(?string $countryCode, mixed $receivedSince, array $filters, mixed $receivedUntil = null): Builder
    {
        $query = MobileSubmission::query()
            ->when($countryCode, fn (Builder $query) => $query->where('country_code', $countryCode))
            ->when($receivedSince, fn (Builder $query) => $query->where('received_at', '>=', $receivedSince))
            ->when($receivedUntil, fn (Builder $query) => $query->where('received_at', '<=', $receivedUntil));

        return $this->applyFilters($query, $filters);
    }

    private function applyDiscoverSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = $this->likeValue($search);
        $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('server_uid', 'like', $like)
                ->orWhere('local_id', 'like', $like)
                ->orWhere('device_id', 'like', $like)
                ->orWhere('form_id', 'like', $like)
                ->orWhere('border_post_code', 'like', $like)
                ->orWhere('border_post_digital_address', 'like', $like)
                ->orWhere('region', 'like', $like)
                ->orWhere('answers', 'like', $like);
        });
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        $fields = $this->filterFieldDefinitions();

        foreach ($filters as $filter) {
            $definition = $fields[$filter['field']] ?? null;
            if (!$definition) {
                continue;
            }

            if (($definition['source'] ?? 'column') === 'location') {
                $hasLocation = (bool) $filter['value'];
                $query->where(function (Builder $query) use ($hasLocation) {
                    if ($hasLocation) {
                        $query->whereNotNull('device_latitude')->whereNotNull('device_longitude');

                        return;
                    }

                    $query->whereNull('device_latitude')->orWhereNull('device_longitude');
                });

                continue;
            }

            if (($definition['source'] ?? 'column') === 'answers') {
                $this->applyAnswerFilter($query, $definition, $filter);

                continue;
            }

            $this->applyColumnFilter($query, $definition['column'], $filter);
        }

        return $query;
    }

    private function applyColumnFilter(Builder $query, string $column, array $filter): void
    {
        $value = $filter['value'];

        match ($filter['operator']) {
            'equals' => $query->where($column, $value),
            'not_equals' => $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, '!=', $value)),
            'contains' => $query->where($column, 'like', $this->likeValue($value)),
            'not_contains' => $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, 'not like', $this->likeValue($value))),
            'empty' => $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, '')),
            'not_empty' => $query->whereNotNull($column)->where($column, '!=', ''),
            default => null,
        };
    }

    private function applyAnswerFilter(Builder $query, array $definition, array $filter): void
    {
        $patterns = collect($definition['answerKeys'])
            ->map(fn (string $key) => $this->likeValue('"'.$key.'"').substr($this->likeValue($filter['value']), 1))
            ->all();

        match ($filter['operator']) {
            'contains', 'equals' => $query->where(fn (Builder $query) => collect($patterns)
                ->each(fn (string $pattern) => $query->orWhere('answers', 'like', $pattern))),
            'not_contains', 'not_equals' => $query->where(fn (Builder $query) => collect($patterns)
                ->each(fn (string $pattern) => $query->where('answers', 'not like', $pattern))),
            default => null,
        };
    }

    private function aggregate(Builder $baseQuery, string $column): array
    {
        return (clone $baseQuery)
            ->selectRaw("coalesce({$column}, 'Unknown') as label, count(*) as total")
            ->groupByRaw("coalesce({$column}, 'Unknown')")
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();
    }

    private function aggregateModules(Builder $baseQuery): array
    {
        return (clone $baseQuery)
            ->selectRaw("coalesce(reporting_module, 'immigration') as module, count(*) as total")
            ->groupByRaw("coalesce(reporting_module, 'immigration')")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => DynamicForm::moduleLabels()[DynamicForm::normalizeModule($row->module)] ?? 'Immigration',
                'value' => DynamicForm::normalizeModule($row->module),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function analysisPayload($rows, int $hours): array
    {
        return [
            'timeline' => $this->timelineBuckets($rows, $hours),
            'statusBreakdown' => $this->topCounts($rows, fn (MobileSubmission $submission) => $submission->status ?: 'unknown'),
            'modules' => $this->topCounts($rows, fn (MobileSubmission $submission) => $submission->reportingModuleLabel()),
            'gpsQuality' => $this->gpsQuality($rows),
            'topDevices' => $this->topCounts($rows, fn (MobileSubmission $submission) => $submission->device_id ?: 'Unknown device', 8),
            'movementTypes' => $this->topCounts($rows, fn (MobileSubmission $submission) => $this->answerValue($submission, 'movement_type') ?: 'Unknown'),
            'decisions' => $this->topCounts($rows, fn (MobileSubmission $submission) => $this->answerValue($submission, 'officer_decision', 'inspection_decision', 'decision', 'admissibility_decision') ?: 'Unknown'),
            'documentTypes' => $this->topCounts($rows, fn (MobileSubmission $submission) => $this->answerValue($submission, 'document_type', 'travel_document_type', 'id_type') ?: 'Unknown'),
            'nationalities' => $this->topCounts($rows, fn (MobileSubmission $submission) => $this->answerValue($submission, 'nationality', 'nationality_code', 'citizenship') ?: 'Unknown'),
            'originDestination' => $this->topCounts($rows, fn (MobileSubmission $submission) => $this->answerValue($submission, 'destination', 'destination_location', 'to_location', 'origin', 'origin_location', 'from_location') ?: 'Unknown'),
            'formVersions' => $this->topCounts($rows, fn (MobileSubmission $submission) => "{$submission->form_id} v{$submission->form_version}", 8),
            'syncLatency' => $this->syncLatency($rows),
            'dataQuality' => $this->dataQuality($rows),
        ];
    }

    private function cachedMapBoundary(?Country $country, CountryBoundaryImporter $boundaries): array
    {
        if (!$country?->boundary_geojson_path) {
            return [null, 0, false];
        }

        $cacheKey = implode(':', [
            'map-boundary',
            $country->code,
            sha1((string) $country->boundary_geojson_path),
            optional($country->updated_at)->timestamp,
            self::MAX_MAP_BOUNDARY_COORDINATES,
        ]);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($country, $boundaries): array {
            return $this->mapBoundary($boundaries->readBoundary($country));
        });
    }

    private function mapBoundary(?array $boundary): array
    {
        if (!$boundary) {
            return [null, 0, false];
        }

        $coordinateCount = $this->boundaryCoordinateCount($boundary);

        if ($coordinateCount <= self::MAX_MAP_BOUNDARY_COORDINATES) {
            return [$boundary, $coordinateCount, false];
        }

        $stride = (int) ceil($coordinateCount / self::MAX_MAP_BOUNDARY_COORDINATES);

        return [$this->simplifyBoundaryNode($boundary, $stride), $coordinateCount, true];
    }

    private function boundaryCoordinateCount(?array $node): int
    {
        if (!$node) {
            return 0;
        }

        return match ($node['type'] ?? null) {
            'FeatureCollection' => collect($node['features'] ?? [])->sum(fn ($feature) => $this->boundaryCoordinateCount($feature)),
            'Feature' => $this->boundaryCoordinateCount($node['geometry'] ?? null),
            'GeometryCollection' => collect($node['geometries'] ?? [])->sum(fn ($geometry) => $this->boundaryCoordinateCount($geometry)),
            'Polygon' => collect($node['coordinates'] ?? [])->sum(fn ($ring) => is_array($ring) ? count($ring) : 0),
            'MultiPolygon' => collect($node['coordinates'] ?? [])->sum(fn ($polygon) => collect($polygon)->sum(fn ($ring) => is_array($ring) ? count($ring) : 0)),
            default => 0,
        };
    }

    private function simplifyBoundaryNode(array $node, int $stride): array
    {
        return match ($node['type'] ?? null) {
            'FeatureCollection' => array_replace($node, [
                'features' => array_map(fn ($feature) => $this->simplifyBoundaryNode($feature, $stride), $node['features'] ?? []),
            ]),
            'Feature' => array_replace($node, [
                'geometry' => isset($node['geometry']) ? $this->simplifyBoundaryNode($node['geometry'], $stride) : null,
            ]),
            'GeometryCollection' => array_replace($node, [
                'geometries' => array_map(fn ($geometry) => $this->simplifyBoundaryNode($geometry, $stride), $node['geometries'] ?? []),
            ]),
            'Polygon' => array_replace($node, [
                'coordinates' => array_map(fn ($ring) => $this->simplifyBoundaryRing($ring, $stride), $node['coordinates'] ?? []),
            ]),
            'MultiPolygon' => array_replace($node, [
                'coordinates' => array_map(
                    fn ($polygon) => array_map(fn ($ring) => $this->simplifyBoundaryRing($ring, $stride), $polygon),
                    $node['coordinates'] ?? []
                ),
            ]),
            default => $node,
        };
    }

    private function simplifyBoundaryRing(array $ring, int $stride): array
    {
        if ($stride <= 1 || count($ring) <= 12) {
            return $ring;
        }

        $lastIndex = count($ring) - 1;
        $simplified = [];

        foreach ($ring as $index => $coordinate) {
            if ($index === 0 || $index === $lastIndex || $index % $stride === 0) {
                $simplified[] = $coordinate;
            }
        }

        return count($simplified) >= 4 ? $simplified : $ring;
    }

    private function timelineBuckets($rows, int $hours): array
    {
        $bucketMinutes = $hours <= 6 ? 30 : ($hours <= 48 ? 60 : 1440);
        $cursor = $this->floorToBucket(now()->subHours($hours), $bucketMinutes);
        $end = now();
        $buckets = [];

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d H:i');
            $buckets[$key] = [
                'key' => $key,
                'label' => $bucketMinutes >= 1440 ? $cursor->format('M j') : $cursor->format('M j H:i'),
                'total' => 0,
            ];
            $cursor = $cursor->copy()->addMinutes($bucketMinutes);
        }

        foreach ($rows as $submission) {
            if (!$submission->received_at) {
                continue;
            }

            $key = $this->floorToBucket($submission->received_at, $bucketMinutes)->format('Y-m-d H:i');
            if (isset($buckets[$key])) {
                $buckets[$key]['total']++;
            }
        }

        return array_values($buckets);
    }

    private function floorToBucket(mixed $time, int $bucketMinutes): mixed
    {
        $bucket = $time->copy()->second(0)->microsecond(0);

        if ($bucketMinutes >= 1440) {
            return $bucket->startOfDay();
        }

        $minutes = ($bucket->hour * 60) + $bucket->minute;
        $floored = intdiv($minutes, $bucketMinutes) * $bucketMinutes;

        return $bucket->startOfDay()->addMinutes($floored);
    }

    private function gpsQuality($rows): array
    {
        $withGps = $rows->filter(fn (MobileSubmission $submission) => $submission->device_latitude !== null && $submission->device_longitude !== null);
        $accurate = $withGps->filter(fn (MobileSubmission $submission) => (float) ($submission->device_location_accuracy_meters ?? 999999) <= 50)->count();
        $approximate = $withGps->filter(function (MobileSubmission $submission) {
            $accuracy = (float) ($submission->device_location_accuracy_meters ?? 999999);

            return $accuracy > 50 && $accuracy <= 250;
        })->count();
        $low = $withGps->count() - $accurate - $approximate;

        return [
            ['label' => 'High accuracy', 'total' => $accurate],
            ['label' => 'Approximate', 'total' => $approximate],
            ['label' => 'Low accuracy', 'total' => max(0, $low)],
            ['label' => 'Missing GPS', 'total' => $rows->count() - $withGps->count()],
        ];
    }

    private function syncLatency($rows): array
    {
        $buckets = [
            'Under 5 min' => 0,
            '5-30 min' => 0,
            '30 min-2 hr' => 0,
            '2-24 hr' => 0,
            'Over 24 hr' => 0,
            'Unknown' => 0,
        ];

        foreach ($rows as $submission) {
            $minutes = $this->syncDelayMinutes($submission);
            if ($minutes === null) {
                $buckets['Unknown']++;
            } elseif ($minutes < 5) {
                $buckets['Under 5 min']++;
            } elseif ($minutes < 30) {
                $buckets['5-30 min']++;
            } elseif ($minutes < 120) {
                $buckets['30 min-2 hr']++;
            } elseif ($minutes < 1440) {
                $buckets['2-24 hr']++;
            } else {
                $buckets['Over 24 hr']++;
            }
        }

        return collect($buckets)
            ->map(fn (int $total, string $label) => compact('label', 'total'))
            ->values()
            ->all();
    }

    private function dataQuality($rows): array
    {
        $missingGps = $rows->filter(fn (MobileSubmission $submission) => $submission->device_latitude === null || $submission->device_longitude === null)->count();
        $lowGps = $rows->filter(function (MobileSubmission $submission): bool {
            if ($submission->device_latitude === null || $submission->device_longitude === null) {
                return false;
            }

            return (float) ($submission->device_location_accuracy_meters ?? 999999) > 250;
        })->count();
        $missingDocument = $rows->filter(fn (MobileSubmission $submission) => $this->answerValue($submission, 'travel_document_number', 'document_number', 'id_number') === null)->count();
        $delayed = $rows->filter(fn (MobileSubmission $submission) => ($this->syncDelayMinutes($submission) ?? 0) >= 1440)->count();

        return [
            ['label' => 'Missing GPS', 'total' => $missingGps],
            ['label' => 'GPS accuracy >250m', 'total' => $lowGps],
            ['label' => 'Missing document number', 'total' => $missingDocument],
            ['label' => 'Sync delay >24h', 'total' => $delayed],
        ];
    }

    private function topCounts($rows, callable $labeler, int $limit = 10): array
    {
        return $rows
            ->map(fn (MobileSubmission $submission) => trim((string) $labeler($submission)) ?: 'Unknown')
            ->countBy()
            ->map(fn (int $total, string $label) => ['label' => $label, 'total' => $total])
            ->sortByDesc('total')
            ->take($limit)
            ->values()
            ->all();
    }

    private function syncDelayMinutes(MobileSubmission $submission): ?int
    {
        if (!$submission->client_created_at || !$submission->received_at) {
            return null;
        }

        return max(0, (int) $submission->client_created_at->diffInMinutes($submission->received_at));
    }

    private function normalizeFilters(mixed $rawFilters): array
    {
        if (is_string($rawFilters)) {
            $decoded = json_decode($rawFilters, true);
            $rawFilters = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawFilters)) {
            return [];
        }

        $definitions = $this->filterFieldDefinitions();
        $filters = [];

        foreach (array_slice($rawFilters, 0, 10) as $rawFilter) {
            if (!is_array($rawFilter)) {
                continue;
            }

            $field = (string) ($rawFilter['field'] ?? '');
            $definition = $definitions[$field] ?? null;
            if (!$definition) {
                continue;
            }

            $operator = (string) ($rawFilter['operator'] ?? ($definition['operators'][0] ?? 'equals'));
            if (!in_array($operator, $definition['operators'], true)) {
                continue;
            }

            $value = $rawFilter['value'] ?? '';
            if (($definition['valueType'] ?? 'text') === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $value = substr(trim((string) $value), 0, 120);
            }

            if (!in_array($operator, ['empty', 'not_empty'], true) && ($definition['valueType'] ?? 'text') !== 'boolean' && $value === '') {
                continue;
            }

            $filters[] = compact('field', 'operator', 'value');
        }

        return $filters;
    }

    private function normalizeLayout(array $layout): array
    {
        $ordered = [];
        foreach ($layout as $panel) {
            if (is_string($panel) && in_array($panel, self::DEFAULT_LAYOUT, true) && !in_array($panel, $ordered, true)) {
                $ordered[] = $panel;
            }
        }

        foreach (self::DEFAULT_LAYOUT as $panel) {
            if (!in_array($panel, $ordered, true)) {
                $ordered[] = $panel;
            }
        }

        return $ordered;
    }

    private function serializedDashboardViews(int $userId): array
    {
        return DashboardView::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (DashboardView $view) => $this->serializeDashboardView($view))
            ->all();
    }

    private function serializeDashboardView(DashboardView $view): array
    {
        return [
            'id' => $view->id,
            'name' => $view->name,
            'description' => $view->description,
            'countryCode' => $view->country_code,
            'timeWindowHours' => $view->time_window_hours,
            'filters' => $view->filters ?: [],
            'layout' => $this->normalizeLayout($view->layout ?: []),
            'isDefault' => $view->is_default,
            'updatedAt' => optional($view->updated_at)->toIso8601String(),
        ];
    }

    private function filterOptions(?Country $country): array
    {
        $countryCode = $country?->code;

        return [
            'statuses' => [
                ['value' => 'accepted', 'label' => 'Accepted'],
                ['value' => 'rejected', 'label' => 'Rejected'],
                ['value' => 'failed', 'label' => 'Failed'],
            ],
            'modules' => collect(DynamicForm::moduleLabels())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'borderPosts' => BorderPost::query()
                ->when($countryCode, fn (Builder $query) => $query->where('country_code', $countryCode))
                ->orderBy('code')
                ->limit(250)
                ->get(['code', 'digital_address', 'name'])
                ->map(fn (BorderPost $post) => [
                    'value' => $post->code,
                    'label' => "{$post->code} - {$post->name}".($post->digital_address ? " ({$post->digital_address})" : ''),
                ])
                ->all(),
            'digitalAddresses' => BorderPost::query()
                ->when($countryCode, fn (Builder $query) => $query->where('country_code', $countryCode))
                ->whereNotNull('digital_address')
                ->orderBy('digital_address')
                ->limit(250)
                ->get(['digital_address', 'name'])
                ->map(fn (BorderPost $post) => ['value' => $post->digital_address, 'label' => "{$post->digital_address} - {$post->name}"])
                ->all(),
            'forms' => DynamicForm::query()
                ->where('is_template', false)
                ->when($countryCode, fn (Builder $query) => $query->where('country_code', $countryCode))
                ->orderBy('form_id')
                ->limit(250)
                ->get(['form_id', 'title'])
                ->map(fn (DynamicForm $form) => ['value' => $form->form_id, 'label' => "{$form->form_id} - {$form->title}"])
                ->all(),
            'regions' => BorderPost::query()
                ->when($countryCode, fn (Builder $query) => $query->where('country_code', $countryCode))
                ->whereNotNull('region')
                ->distinct()
                ->orderBy('region')
                ->pluck('region')
                ->map(fn (string $region) => ['value' => $region, 'label' => $region])
                ->all(),
        ];
    }

    private function publicFilterFields(): array
    {
        $fields = [];

        foreach ($this->filterFieldDefinitions() as $key => $definition) {
            $fields[] = [
                'key' => $key,
                'label' => $definition['label'],
                'operators' => $definition['operators'],
                'valueType' => $definition['valueType'],
                'optionSource' => $definition['optionSource'] ?? null,
            ];
        }

        return $fields;
    }

    private function filterFieldDefinitions(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'column' => 'status',
                'valueType' => 'select',
                'optionSource' => 'statuses',
                'operators' => ['equals', 'not_equals'],
            ],
            'reporting_module' => [
                'label' => 'Module',
                'column' => 'reporting_module',
                'valueType' => 'select',
                'optionSource' => 'modules',
                'operators' => ['equals', 'not_equals'],
            ],
            'border_post_code' => [
                'label' => 'Border Post',
                'column' => 'border_post_code',
                'valueType' => 'text',
                'optionSource' => 'borderPosts',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
            ],
            'border_post_digital_address' => [
                'label' => 'Digital Address',
                'column' => 'border_post_digital_address',
                'valueType' => 'text',
                'optionSource' => 'digitalAddresses',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
            ],
            'region' => [
                'label' => 'Region',
                'column' => 'region',
                'valueType' => 'text',
                'optionSource' => 'regions',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
            ],
            'form_id' => [
                'label' => 'Form',
                'column' => 'form_id',
                'valueType' => 'text',
                'optionSource' => 'forms',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains'],
            ],
            'device_id' => [
                'label' => 'Device ID',
                'column' => 'device_id',
                'valueType' => 'text',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains'],
            ],
            'server_uid' => [
                'label' => 'Server Receipt',
                'column' => 'server_uid',
                'valueType' => 'text',
                'operators' => ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'],
            ],
            'has_location' => [
                'label' => 'GPS Location',
                'source' => 'location',
                'valueType' => 'boolean',
                'operators' => ['equals'],
            ],
            'movement_type' => [
                'label' => 'Movement Type',
                'source' => 'answers',
                'answerKeys' => ['movement_type'],
                'valueType' => 'text',
                'operators' => ['contains', 'not_contains'],
            ],
            'document_number' => [
                'label' => 'Document Number',
                'source' => 'answers',
                'answerKeys' => ['travel_document_number', 'document_number', 'id_number'],
                'valueType' => 'text',
                'operators' => ['contains', 'not_contains'],
            ],
            'traveller_name' => [
                'label' => 'Traveller Name',
                'source' => 'answers',
                'answerKeys' => ['traveller_full_name', 'full_name', 'traveller_name'],
                'valueType' => 'text',
                'operators' => ['contains', 'not_contains'],
            ],
        ];
    }

    private function operatorLabels(): array
    {
        return [
            'equals' => 'is',
            'not_equals' => 'is not',
            'contains' => 'contains',
            'not_contains' => 'does not contain',
            'empty' => 'is empty',
            'not_empty' => 'is not empty',
        ];
    }

    private function likeValue(string $value): string
    {
        return '%'.$value.'%';
    }

    private function answerValue(MobileSubmission $submission, string ...$keys): ?string
    {
        $answers = $submission->answers ?: [];

        foreach ($keys as $key) {
            $value = $answers[$key] ?? null;
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function serializeSubmission(MobileSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'countryCode' => $submission->country_code,
            'borderPostCode' => $submission->border_post_code,
            'borderPostDigitalAddress' => $submission->border_post_digital_address,
            'region' => $submission->region,
            'reportingModule' => DynamicForm::normalizeModule($submission->reporting_module),
            'reportingModuleLabel' => $submission->reportingModuleLabel(),
            'formId' => $submission->form_id,
            'formVersion' => $submission->form_version,
            'deviceId' => $submission->device_id,
            'localId' => $submission->local_id,
            'serverId' => $submission->server_uid,
            'status' => $submission->status,
            'rejectionReason' => $submission->rejection_reason,
            'receivedAt' => optional($submission->received_at)->toIso8601String(),
            'clientCreatedAt' => optional($submission->client_created_at)->toIso8601String(),
            'clientSyncedAt' => optional($submission->client_synced_at)->toIso8601String(),
            'syncDelayMinutes' => $this->syncDelayMinutes($submission),
            'latitude' => $submission->device_latitude !== null ? (float) $submission->device_latitude : null,
            'longitude' => $submission->device_longitude !== null ? (float) $submission->device_longitude : null,
            'accuracyMeters' => $submission->device_location_accuracy_meters !== null ? (float) $submission->device_location_accuracy_meters : null,
            'locationCapturedAt' => optional($submission->device_location_captured_at)->toIso8601String(),
            'movementType' => $this->answerValue($submission, 'movement_type'),
            'travellerName' => $this->answerValue($submission, 'traveller_full_name', 'full_name', 'traveller_name'),
            'documentNumber' => $this->answerValue($submission, 'travel_document_number', 'document_number', 'id_number'),
        ];
    }
}
