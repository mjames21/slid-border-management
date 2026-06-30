# BorderReach FSD Compliance Notes

## Laravel Web Backend

Implemented in `webapp`:

- Jetstream/Fortify admin authentication, logout, profile management, teams, two-factor authentication, passkeys, browser sessions, and `is_admin` route protection.
- XLSForm upload, server-side storage, compilation, versioning, warnings, and publishing.
- Runtime schema output with `formId`, `version`, `title`, `defaultLanguage`, `fields`, `choiceLists`, and compile metadata.
- Mobile auth endpoints for login, `/me`, logout, and scoped Sanctum tokens.
- Mobile config endpoint returning only published form versions.
- Batch submission endpoint with published-schema validation and accepted/rejected response payloads.
- Admin submission list, filters, details, CSV export, and JSON export.
- Security controls for throttling, admin-only form management, bearer-token mobile APIs, validation limits, and browser headers.

Note: `webapp` has been merged into a complete Laravel 12 project with Sanctum and PhpSpreadsheet installed.

## Android Mobile App

Implemented in `reporting_app`:

- First online login using the Laravel mobile auth API.
- Local auth session storage without storing the raw server password.
- Active form download and Room caching.
- Dynamic rendering from standards-backed runtime schema fields.
- Step-based ODK-style form capture.
- Admin setup QR scan using CameraX and ML Kit barcode scanning.
- Passport MRZ scan assist using CameraX and ML Kit text recognition.
- Relevance handling, supported calculate fields, and required-field validation.
- Local draft and pending-sync submission storage.
- Manual sync and WorkManager sync when network is available.
- Local status updates for accepted and rejected sync responses.

Future hardening scope:

- Biometrics/device unlock policy, camera/photo evidence fields, push notifications, geofencing enforcement, release signing automation, and advanced XLSForm functions.
