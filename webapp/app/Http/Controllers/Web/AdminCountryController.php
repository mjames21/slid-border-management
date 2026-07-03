<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCountryBrandingRequest;
use App\Models\Country;
use App\Services\AuditLogger;
use App\Services\CountryBoundaryImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminCountryController extends Controller
{
    public function index(): View
    {
        $countries = Country::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.countries.index', compact('countries'));
    }

    public function edit(Country $country): View
    {
        return view('admin.countries.edit', compact('country'));
    }

    public function update(UpdateCountryBrandingRequest $request, Country $country, AuditLogger $audit, CountryBoundaryImporter $boundaries): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            if ($country->logo_path) {
                Storage::disk('public')->delete($country->logo_path);
            }

            $file = $request->file('logo');
            $validated['logo_path'] = $file->store("country-branding/{$country->code}", 'public');
            $validated['logo_mime_type'] = $file->getMimeType() ?: 'image/png';
        }

        if ($request->hasFile('boundary_file')) {
            if ($country->boundary_geojson_path) {
                Storage::disk('public')->delete($country->boundary_geojson_path);
            }

            $validated = array_merge(
                $validated,
                $boundaries->import($country, $request->file('boundary_file'))
            );
        }

        $country->fill([
            'name' => $validated['name'],
            'tenant_slug' => $validated['tenant_slug'],
            'tenant_status' => $validated['tenant_status'],
            'deployment_plan' => $validated['deployment_plan'],
            'deployment_type' => $validated['deployment_type'],
            'support_tier' => $validated['support_tier'],
            'data_region' => $validated['data_region'] ?: null,
            'primary_domain' => $validated['primary_domain'] ?: null,
            'immigration_agency' => $validated['immigration_agency'] ?: null,
            'app_title' => $validated['app_title'],
            'app_subtitle' => $validated['app_subtitle'] ?: null,
            'timezone' => $validated['timezone'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'logo_path' => $validated['logo_path'] ?? $country->logo_path,
            'logo_mime_type' => $validated['logo_mime_type'] ?? $country->logo_mime_type,
            'boundary_geojson_path' => $validated['boundary_geojson_path'] ?? $country->boundary_geojson_path,
            'boundary_source_name' => $validated['boundary_source_name'] ?? $country->boundary_source_name,
            'boundary_source_type' => $validated['boundary_source_type'] ?? $country->boundary_source_type,
            'boundary_imported_at' => $validated['boundary_imported_at'] ?? $country->boundary_imported_at,
        ])->save();

        $audit->record('admin.country_branding_updated', $request->user(), metadata: [
            'country_code' => $country->code,
            'tenant_slug' => $country->tenant_slug,
            'tenant_status' => $country->tenant_status,
            'deployment_plan' => $country->deployment_plan,
            'deployment_type' => $country->deployment_type,
            'app_title' => $country->app_title,
            'has_logo' => $country->logo_path !== null,
            'has_boundary' => $country->boundary_geojson_path !== null,
        ], request: $request);

        return redirect()->route('admin.countries.index')->with('status', "{$country->name} branding updated.");
    }

    public function updateBoundary(Request $request, Country $country, AuditLogger $audit, CountryBoundaryImporter $boundaries): RedirectResponse
    {
        $validated = $request->validate([
            'boundary_file' => ['required', 'file', 'max:20480', 'extensions:geojson,json,zip,shp'],
        ], $this->boundaryUploadMessages());

        if ($country->boundary_geojson_path) {
            Storage::disk('public')->delete($country->boundary_geojson_path);
        }

        $boundaryData = $boundaries->import($country, $validated['boundary_file']);
        $country->forceFill($boundaryData)->save();

        $audit->record('admin.country_boundary_updated', $request->user(), metadata: [
            'country_code' => $country->code,
            'boundary_source_name' => $country->boundary_source_name,
            'boundary_source_type' => $country->boundary_source_type,
        ], request: $request);

        return back()->with('status', "{$country->name} map boundary uploaded.");
    }

    private function boundaryUploadMessages(): array
    {
        return [
            'boundary_file.required' => 'Choose a GeoJSON or zipped polygon shapefile before uploading the boundary.',
            'boundary_file.file' => 'The boundary upload was not received as a valid file. Check the server upload settings and try again.',
            'boundary_file.max' => 'The boundary file is larger than 20MB. Upload a smaller GeoJSON or zipped shapefile.',
            'boundary_file.extensions' => 'Upload GeoJSON, JSON, ZIP shapefile, or SHP only.',
            'boundary_file.uploaded' => 'The boundary file could not be uploaded. The server upload limit may be too low; set upload_max_filesize to at least 25M and post_max_size to at least 30M.',
        ];
    }
}
