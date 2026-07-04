<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use App\Services\CountryBrandingService;
use App\Services\LocationOptionCatalog;
use Illuminate\Http\JsonResponse;

class MobileConfigController extends Controller
{
    public function __invoke(LocationOptionCatalog $locations, CountryBrandingService $branding): JsonResponse
    {
        if (!request()->user()?->tokenCan('mobile:read')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = request()->user()?->load(['borderPost.country', 'country']);
        $countryCode = $user?->operationalCountryCode() ?? 'SLE';

        $forms = DynamicFormVersion::query()
            ->with('form')
            ->where('is_published', true)
            ->whereHas('form', fn ($query) => $query
                ->where('country_code', $countryCode)
                ->where('is_template', false))
            ->latest('updated_at')
            ->get()
            ->map(function (DynamicFormVersion $version) use ($locations, $user): array {
                $schema = $locations->hydrateSchema($version->compiled_schema, $user?->borderPost);
                $schema['reportingModule'] = DynamicForm::normalizeModule(
                    $version->form?->reporting_module ?? ($schema['reportingModule'] ?? null)
                );
                $schema['standardReference'] = trim((string) ($schema['standardReference'] ?? ''))
                    ?: DynamicForm::standardReferenceForModule($schema['reportingModule']);

                return $schema;
            })
            ->values();

        return response()->json([
            'branding' => $branding->payload($user?->borderPost?->country ?: $user?->country),
            'activeForms' => $forms,
        ]);
    }
}
