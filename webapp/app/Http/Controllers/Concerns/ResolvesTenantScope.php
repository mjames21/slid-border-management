<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ResolvesTenantScope
{
    protected function selectedCountryCode(Request $request, string $parameter = 'country_code'): ?string
    {
        $requested = strtoupper(trim((string) $request->query($parameter, '')));

        if ($request->user()?->canManageAllTenants()) {
            return $requested !== '' ? $requested : null;
        }

        return $request->user()?->tenantCountryCode() ?: '__none__';
    }

    protected function defaultCountryCode(Request $request, string $fallback = 'SLE'): string
    {
        return $this->selectedCountryCode($request) ?: $fallback;
    }

    protected function countryQueryForUser(Request $request): Builder
    {
        $query = Country::query();

        if (! $request->user()?->canManageAllTenants()) {
            $query->whereKey($request->user()?->tenantCountryCode() ?: '__none__');
        }

        return $query;
    }

    protected function countriesForUser(Request $request)
    {
        return $this->countryQueryForUser($request)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function assertCanAccessCountry(Request $request, ?string $countryCode): void
    {
        abort_unless($request->user()?->canAccessCountry($countryCode), 403, 'This record belongs to another BorderReach workspace.');
    }

    protected function assertCanAccessRecordCountry(Request $request, object $record, string $attribute = 'country_code'): void
    {
        $this->assertCanAccessCountry($request, $record->{$attribute} ?? null);
    }
}
