<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFrequentLocationsRequest;
use App\Models\Country;
use App\Models\FrequentLocation;
use App\Services\AuditLogger;
use App\Services\LocationOptionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminLocationController extends Controller
{
    use ResolvesTenantScope;

    public function index(Request $request): View
    {
        $selectedCountry = $this->selectedCountryCode($request);

        $locations = FrequentLocation::query()
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->orderBy('country_code')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $counts = FrequentLocation::query()
            ->where('is_active', true)
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->selectRaw('country_code, count(*) as total')
            ->groupBy('country_code')
            ->pluck('total', 'country_code');

        return view('admin.locations.index', [
            'locations' => $locations,
            'counts' => $counts,
            'countries' => $this->countriesForUser($request),
            'filters' => ['country_code' => $selectedCountry],
        ]);
    }

    public function store(UploadFrequentLocationsRequest $request, LocationOptionCatalog $catalog, AuditLogger $audit): RedirectResponse
    {
        $path = $request->file('file')->store('location-imports');
        $allowedCountries = $request->user()?->canManageAllTenants()
            ? null
            : [$request->user()?->tenantCountryCode()];
        $result = $catalog->importSpreadsheet(Storage::path($path), $allowedCountries);

        $audit->record('admin.frequent_locations_imported', $request->user(), metadata: [
            'path' => $path,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ], request: $request);

        $message = "Locations imported: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return redirect()
            ->route('admin.locations.index')
            ->with('status', $message)
            ->with('location_import_errors', array_slice($result['errors'], 0, 10));
    }

}
