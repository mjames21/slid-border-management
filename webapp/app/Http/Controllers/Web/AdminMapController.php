<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMapController extends Controller
{
    public function index(Request $request): View
    {
        $selectedCountryCode = strtoupper((string) $request->query('country_code', 'SLE'));
        $selectedCountry = Country::query()->find($selectedCountryCode) ?: Country::query()->find('SLE');

        return view('admin.map.index', [
            'countries' => Country::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'selectedCountry' => $selectedCountry,
        ]);
    }
}
