# BorderReach Android Mobile App

Android Studio project for the offline-first BorderReach mobile app used by border officers in remote posts.

## Current features

- Online first login against the Laravel mobile auth API
- Editable server URL, similar to ODK Collect
- Admin-generated setup QR scanning with the device camera
- Local auth session storage for offline use after first login
- Download and cache active published runtime forms
- Standards metadata display for immigration, customs, health, and security forms
- Render dynamic fields from the downloaded schema
- ODK-style step-by-step form screens instead of one long form
- Passport MRZ scanning with CameraX and ML Kit text recognition, then prefill of matching form fields
- Required-field validation before finalizing submissions
- Save drafts and pending submissions locally with Room
- Manual sync and WorkManager network-based background sync
- Update local sync status from accepted/rejected backend responses
- Stable per-install device ID for login and sync identity
- Assigned officer role and border post shown after login
- Assigned border-post longitude, latitude, and allowed radius cached for offline use
- Device GPS longitude, latitude, accuracy, and capture time attached to synced submissions when location permission is granted
- Country-specific title and logo synced from Laravel and cached for offline use
- Versioned Room migrations to preserve offline data during app upgrades
- SQLCipher-encrypted local databases for auth sessions, forms, drafts, and pending submissions
- Debug-only local HTTP access; release builds should point to an HTTPS backend

## Stack

- Kotlin
- Jetpack Compose
- Room
- WorkManager
- CameraX
- ML Kit
- SQLCipher
- Android Studio

## Import into Android Studio

1. Open Android Studio.
2. Choose **Open**.
3. Select this folder: `reporting_app`.
4. Let Gradle sync.
5. Select the `app` run configuration.
6. Run on an emulator or Android device.

From terminal, verify the import/build with:

```bash
JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" sh ./gradlew testDebugUnitTest
JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" sh ./gradlew assembleDebug
```

## Backend connection

The login screen includes an editable **Server URL** field, similar to ODK Collect. The value is saved locally and used for login, branding, form download, logout, and submission sync.

Debug builds default to `http://10.0.2.2:8000/`, which maps an Android emulator to the Laravel dev server running on the host machine. On a real Android device, use the computer's LAN address instead, for example `http://192.168.1.20:8000/`, and make sure the phone and computer are on the same network.

Release builds use the `API_BASE_URL` build config value in `app/build.gradle.kts`; set that to the production HTTPS endpoint before field deployment.

## QR setup

Admins can generate a mobile setup QR from the Laravel `/admin/users` page. The QR contains the server URL, officer email, a temporary password, and an optional device name. On Android, tap **Scan Setup QR** on the login screen and grant camera access; the app scans the QR directly without needing a separate scanner app. If the camera is unavailable, enter the server URL, email, password, and device name manually.

Use an assigned Laravel mobile user for first login during local development:

```text
officer@slid.local
Officer123!
```

## Country branding

The Laravel country profile controls the in-app title, subtitle, and logo. On startup, Android calls `/api/mobile/branding` and stores the result locally; after login, auth/config sync confirms the officer's assigned country branding. The login, home, form, and stored-submission screens use the cached branding offline. The launcher icon and earliest Android OS splash screen are still packaged with the APK; deploy one generic APK and use Laravel country settings for operational branding.

## Local data protection

The app encrypts its Room databases with SQLCipher. A per-install database passphrase is generated on first launch, encrypted with Android Keystore, and stored in app-private preferences. Backups are disabled in the manifest so tokens and offline traveller reports are not copied into device backups.

If upgrading an emulator that already had a plaintext development database, uninstall the app or clear app data before installing this encrypted build. Production deployments should start with this encrypted build so offline data is encrypted from the first capture.

## ODK-style storage and sync

Finalized reports are saved locally first, then submitted to Laravel when the server is reachable. Failed sends remain queued, keep the last sync error, track retry count and last attempt time, and continue retrying through the background worker. Accepted reports store the Laravel server receipt ID and received timestamp so officers and admins can reconcile phone records with dashboard records.

## Current product scope

Implemented mobile scope covers offline login/session, QR setup, dynamic standards-backed forms, step-based capture, MRZ scan assist, GPS custody metadata, drafts, encrypted local storage, and background sync. Future hardening can add biometric device unlock, richer photo evidence fields, enforced geofence blocking, push notifications, and signed release distribution.
