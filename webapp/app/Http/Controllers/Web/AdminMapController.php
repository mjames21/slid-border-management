<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMapController extends Controller
{
    use ResolvesTenantScope;

    public function index(Request $request): View
    {
        $selectedCountryCode = $this->defaultCountryCode($request);
        $selectedCountry = $this->countryQueryForUser($request)->find($selectedCountryCode)
            ?: $this->countryQueryForUser($request)->first();

        return view('admin.map.index', [
            'countries' => $this->countriesForUser($request),
            'selectedCountry' => $selectedCountry,
        ]);
    }
}
