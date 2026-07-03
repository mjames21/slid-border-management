<?php

namespace App\Http\Requests;

use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCountryBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $tenantSlug = trim((string) $this->input('tenant_slug'));

        $this->merge([
            'tenant_slug' => Str::slug($tenantSlug !== '' ? $tenantSlug : (string) $this->input('name')),
            'tenant_status' => (string) $this->input('tenant_status', Country::TENANT_STATUS_IMPLEMENTATION),
            'deployment_plan' => (string) $this->input('deployment_plan', Country::PLAN_PROGRAM),
            'deployment_type' => (string) $this->input('deployment_type', Country::DEPLOYMENT_HOSTED),
            'support_tier' => trim((string) $this->input('support_tier', 'standard')),
            'data_region' => trim((string) $this->input('data_region')),
            'primary_domain' => trim((string) $this->input('primary_domain')),
            'app_title' => trim((string) $this->input('app_title')),
            'app_subtitle' => trim((string) $this->input('app_subtitle')),
            'immigration_agency' => trim((string) $this->input('immigration_agency')),
            'timezone' => trim((string) $this->input('timezone', 'Africa/Freetown')),
        ]);
    }

    public function rules(): array
    {
        $country = $this->route('country');

        return [
            'name' => ['required', 'string', 'max:255'],
            'tenant_slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('countries', 'tenant_slug')->ignore($country?->getKey(), 'code'),
            ],
            'tenant_status' => ['required', Rule::in(array_keys(Country::tenantStatusLabels()))],
            'deployment_plan' => ['required', Rule::in(array_keys(Country::deploymentPlanLabels()))],
            'deployment_type' => ['required', Rule::in(array_keys(Country::deploymentTypeLabels()))],
            'support_tier' => ['required', 'string', 'max:32'],
            'data_region' => ['nullable', 'string', 'max:120'],
            'primary_domain' => ['nullable', 'string', 'max:255'],
            'immigration_agency' => ['nullable', 'string', 'max:255'],
            'app_title' => ['required', 'string', 'max:80'],
            'app_subtitle' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'boundary_file' => ['nullable', 'file', 'max:20480', 'extensions:geojson,json,zip,shp'],
        ];
    }

    public function messages(): array
    {
        return [
            'boundary_file.file' => 'The country boundary upload was not received as a valid file. Check the server upload settings and try again.',
            'boundary_file.max' => 'The country boundary file is larger than 20MB. Upload a smaller GeoJSON or zipped shapefile.',
            'boundary_file.extensions' => 'Upload GeoJSON, JSON, ZIP shapefile, or SHP only.',
            'boundary_file.uploaded' => 'The country boundary file could not be uploaded. The server upload limit may be too low; set upload_max_filesize to at least 25M and post_max_size to at least 30M.',
        ];
    }
}
