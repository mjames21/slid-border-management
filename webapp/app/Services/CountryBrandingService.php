<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Storage;

class CountryBrandingService
{
    public function payload(?Country $country): array
    {
        if (!$country) {
            return [
                'countryCode' => null,
                'countryName' => 'BorderReach',
                'agencyName' => 'BorderReach',
                'appTitle' => 'BorderReach',
                'appSubtitle' => 'Border reporting software',
                'logoMimeType' => 'image/png',
                'logoBase64' => $this->logoBase64(null),
            ];
        }

        return [
            'countryCode' => $country->code,
            'countryName' => $country->name,
            'agencyName' => $country->immigration_agency,
            'appTitle' => $country?->app_title ?: $this->defaultTitle($country),
            'appSubtitle' => $country?->app_subtitle ?: $country?->immigration_agency,
            'logoMimeType' => $this->logoMimeType($country),
            'logoBase64' => $this->logoBase64($country),
        ];
    }

    private function defaultTitle(?Country $country): string
    {
        return $country?->code === 'SLE'
            ? 'SLID Border Reporting'
            : trim(($country?->name ?? 'Immigration').' Border Reporting');
    }

    private function logoMimeType(?Country $country): ?string
    {
        if ($country?->logo_path && Storage::disk('public')->exists($country->logo_path)) {
            return $country->logo_mime_type ?: 'image/png';
        }

        return 'image/png';
    }

    private function logoBase64(?Country $country): ?string
    {
        if ($country?->logo_path && Storage::disk('public')->exists($country->logo_path)) {
            return base64_encode(Storage::disk('public')->get($country->logo_path));
        }

        $fallback = public_path('images/borderreach-mark.png');

        return is_file($fallback) ? base64_encode(file_get_contents($fallback)) : null;
    }
}
