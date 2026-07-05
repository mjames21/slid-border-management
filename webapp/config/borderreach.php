<?php

$deploymentMode = strtolower((string) env('BORDERREACH_DEPLOYMENT_MODE', 'platform'));
$tenantCountryCode = strtoupper((string) env('BORDERREACH_TENANT_COUNTRY_CODE', env('MOBILE_DEFAULT_COUNTRY_CODE', 'SLE')));

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment mode
    |--------------------------------------------------------------------------
    |
    | platform: managed BorderReach with workspace/deployment requests and
    |           platform administrators who can see every tenant.
    | client:   private country/agency deployment. Public request pages are
    |           disabled and administrators are scoped to one tenant.
    |
    */
    'deployment_mode' => $deploymentMode,
    'platform_mode' => in_array($deploymentMode, ['platform', 'managed', 'hosted'], true),
    'tenant_country_code' => $tenantCountryCode !== '' ? $tenantCountryCode : 'SLE',

    'seed' => [
        'admin_name' => env('BORDERREACH_SEED_ADMIN_NAME', 'BorderReach Admin'),
        'admin_email' => env('BORDERREACH_SEED_ADMIN_EMAIL', 'admin@slid.local'),
        'admin_password' => env('BORDERREACH_SEED_ADMIN_PASSWORD', env('SEED_DEMO_ADMIN_PASSWORD', 'Password123!')),
        'demo_officer_email' => env('BORDERREACH_SEED_OFFICER_EMAIL', 'officer@slid.local'),
        'demo_officer_password' => env('BORDERREACH_SEED_OFFICER_PASSWORD', env('SEED_DEMO_OFFICER_PASSWORD', 'Officer123!')),
        'border_officer_password' => env('BORDERREACH_SEED_BORDER_OFFICER_PASSWORD', env('SEED_BORDER_OFFICER_PASSWORD', 'Officer123!')),
    ],
];
