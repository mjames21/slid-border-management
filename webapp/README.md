# BorderReach Laravel Backend

This is the Laravel 12 backend for BorderReach, an offline-first single-window border reporting platform for national immigration, customs, health, and security oversight.

It includes:
- Laravel form builder with versioned mobile schemas
- full ICAO Doc 9303 inspection starter template with selectable fields
- WCO Data Model customs declaration starter template
- WHO IHR point-of-entry health screening starter template
- border security incident starter template
- frequent cross-border location catalog upload
- tenant profiles for hosted, private-cloud, or self-hosted deployments
- configurable tenant app title, subtitle, and mobile logo
- tenant boundary upload with live report mapping
- saved live dashboard views with visual filters and panel order
- XLSForm import + compile
- publish versions
- mobile config API
- Sanctum mobile auth
- mobile submission sync
- admin web UI
- submission export
- schema validation
- sample tests
- the uploaded XLSForm fixture

## Current project status

This folder has been merged into a complete Laravel 12 application with:

- Laravel Framework 12.x
- Laravel Sanctum
- Laravel Jetstream with Livewire, teams, two-factor authentication, passkeys, profile management, and browser sessions
- PhpSpreadsheet
- SQLite local development database
- Admin middleware alias registered in `bootstrap/app.php`
- API routes registered in `bootstrap/app.php`
- Admin and XLSForm commands registered in `bootstrap/app.php`
- Public self-registration disabled for enterprise-controlled account provisioning
- Border-post assignment for mobile users
- Mobile device registration and sync custody metadata
- Audit events for mobile login/sync, form import/publish, and exports
- Tenant-scoped forms, mobile users, devices, submissions, and exports

## Deployment model

The platform can run as a hosted multi-tenant deployment, private cloud, or a self-hosted government system. The `countries` table currently stores tenant/deployment profiles because many border agencies operate nationally, but the product should be understood as a generic BorderReach tenant model rather than a country-specific build.

Example tenants can be national agencies, border-service programs, regional pilots, or dedicated government installations.

Border posts, users, forms, devices, and mobile submissions carry `country_code` as the current tenant/deployment key. Android mobile config only returns published forms for the officer's assigned tenant, and synced submissions are stamped with the operational tenant, border post, digital address, region, officer, device, and reporting module.

Admins can manage tenant branding at `/admin/countries`. The mobile app receives the configured app title, subtitle, and logo through `/api/mobile/branding`, mobile auth, and mobile config APIs, then caches them for offline use. Set `MOBILE_DEFAULT_COUNTRY_CODE` to the tenant/deployment code for a dedicated backend so the login screen can be branded before sign-in. This lets the same Android application package be deployed to any tenant by changing the Laravel tenant profile instead of rebuilding the mobile app. Android launcher icons and the earliest OS splash screen remain packaged assets; in-app loading and workspace branding are synced from Laravel.

## Dynamic form builder

Admins can build mobile forms at `/admin/forms` using **Build Form**. Each save creates a new version; publishing a version makes it available to Android devices through `/api/mobile/config`.

The default starter is the full ICAO Doc 9303 inspection template. It is organized around border operation context, document classification, VIZ holder identity, MRZ capture, VIZ/MRZ comparison, physical security inspection, eMRTD chip checks, visa/travel authorization, travel context, and officer decision. Customs, health, and security templates carry their own standards baselines. Admins can keep every field selected or clear fields/sections that do not apply to a border post before saving.

XLSForm upload remains available as an import path for existing Kobo/ODK-style assets.

## Frequent border locations

Admins can upload common from/to places at `/admin/locations`. These lists are used by builder select fields with option sources:

- `locations:all`
- `locations:SLE`
- `locations:GIN`
- `locations:LBR`

CSV/XLS/XLSX uploads should include `country` and `name`; optional columns are `admin_area`, `category`, `aliases`, and `sort_order`.

```csv
country,name,admin_area,category,aliases,sort_order
SLE,Kambia,Kambia,town,,10
GIN,Pamelap,Forecariah,border town,,20
LBR,Bo Waterside,Grand Cape Mount,border town,,30
```

The ICAO Doc 9303 template and the basic border movement template include From Location and To Location fields backed by this catalog.

## Enterprise access model

Admin accounts manage forms, submissions, and border-user provisioning from the web UI. Mobile app access is limited to active users assigned to a border post. This keeps every synced submission tied to an officer, device, border post, digital address, and region.

Admins manage border posts at `/admin/border-posts`. Each post gets a stable digital address and can include longitude, latitude, and an optional allowed radius in meters. These values are included in the mobile auth assignment and cached by Android for offline display and future location QA.

Admins can generate a mobile setup QR from `/admin/users`. Open a user, choose **Setup QR**, enter the server URL the phone can reach, then generate the QR. This resets that user's password to a fresh temporary password and encodes the server URL, email, temporary password, and optional device name for Android setup.

Android submissions also carry the device GPS location when the officer has granted location permission. The sync API stores device longitude, latitude, accuracy, capture time, client sync attempt time, and a stable server receipt ID on each mobile submission, and exports include those fields.

For local development, the non-production seeder creates these demo accounts:

```text
Web admin:
admin@slid.local
Password123!

Android officer:
officer@slid.local
Officer123!
```

The officer is assigned to Bendu `BEN-LND` with digital address `SLE-BP-BEN-LND`. See `../docs/testing/demo-end-to-end-test-guide.md` for the full web-to-mobile test flow.

## Live operations dashboard

Admins use `/admin/dashboard` as the real-time border operations view. The dashboard polls `/admin/dashboard/data` every 10 seconds, plots synced Android reports with GPS coordinates, and aggregates activity by border post, form, and region.

Country boundary files are managed from `/admin/countries`. Upload WGS84 GeoJSON, zipped GeoJSON, or a WGS84 polygon shapefile to draw the country map behind the report points. The current importer intentionally stores normalized GeoJSON in Laravel storage so the dashboard can render without third-party map services or external JavaScript CDNs.

The dashboard includes saved operations views. Each admin can save a named view with country, time window, visual filter rows, default status, and panel order. Filters are server-side whitelisted and currently cover status, border post, digital address, region, form, device ID, server receipt, GPS presence, movement type, document number, and traveller name. The Discover search can look up recent records by receipt, local ID, device, form, post, digital address, region, traveller, document, or answer content.

The ODK-style sync quality panels show offline delay buckets, GPS/data quality flags, form version mix, nationalities, device activity, and latest matching records. This keeps the dashboard useful for remote border posts where reports may arrive hours after capture.

Each production tenant should upload its own official boundary and branding from the tenant profile screen without rebuilding the Android app.

## Commands
```bash
composer install
php artisan migrate
php artisan admin:create-user
php artisan forms:import-fixture tests/Fixtures/slid_border_reporting.xlsx --publish --title="BorderReach Sample Border Reporting"
npm install
npm run build
php artisan test
composer run serve
```

`composer run serve` starts Laravel with GIS-safe upload limits for country boundary files. If you start the server manually, use:

```bash
php -d upload_max_filesize=25M -d post_max_size=30M -d max_execution_time=3600 -d max_input_time=120 artisan serve --host=0.0.0.0 --port=8000
```

## Production hosting checklist

Use PHP 8.4.1 or newer for hosting. The current lock file includes packages that require PHP 8.4.1+, so deploying with PHP 8.2 will fail even though Laravel 12 itself can run on lower versions.

Security hardening is tracked against the official OWASP Top 10:2025 checklist in `docs/security/owasp-top-10-2025-hardening.md`.

1. Copy `.env.production.example` to `.env` on the server.
2. Set `APP_KEY`, `APP_URL`, `MOBILE_SETUP_HOST`, `TRUSTED_HOSTS`, database credentials, mail credentials, and a controlled `TRUSTED_PROXIES` value if the app is behind Nginx, a load balancer, Cloudflare, Forge, or another reverse proxy.
3. Keep `APP_ENV=production`, `APP_DEBUG=false`, `APP_FORCE_HTTPS=true`, `SESSION_ENCRYPT=true`, and `SESSION_SECURE_COOKIE=true`.
4. Point the web server document root to `webapp/public` only. Never expose the project root, `.env`, `storage`, `database`, or `vendor` as public web paths.
5. Set PHP/Nginx upload limits for GIS boundaries: `upload_max_filesize=25M`, `post_max_size=30M`, and web server request body size at least `30M`.
6. Install and build:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

6. Create the first HQ admin with `php artisan admin:create-user`, then use the web UI to create border officers and mobile setup QR codes.
7. Run a queue worker in production so sync, imports, and future background jobs do not depend on a browser request:

```bash
php artisan queue:work --tries=3 --timeout=90
```

8. Use HTTPS for the hosted API before real field testing. Android release builds should use `https://...` in QR setup; debug builds can still use LAN HTTP for local pilots.
