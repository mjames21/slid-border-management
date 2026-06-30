<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\CountryBrandingService;
use Illuminate\Http\JsonResponse;

class MobileBrandingController extends Controller
{
    public function __invoke(CountryBrandingService $branding): JsonResponse
    {
        $countryCode = strtoupper((string) request('country', config('services.mobile.default_country_code', 'SLE')));
        $country = Country::query()->find($countryCode) ?: Country::query()->find('SLE');

        return response()->json($branding->payload($country));
    }
}
