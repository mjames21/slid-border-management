# BorderReach

Offline-first single-window border reporting for national immigration, customs, health, and security oversight.

The workspace has two applications:

- `webapp` - Laravel 12 backend, admin console, form builder, dashboards, mobile APIs, and sync endpoints.
- `reporting_app` - Android Kotlin/Jetpack Compose app for officer field capture.

## Product Positioning

BorderReach is not trying to be a general survey platform. It is a border-operations platform with standards-backed immigration, customs, health, and security workflows. See `docs/product/why-borderreach.md` for the comparison against KoboToolbox, ODK, and SurveyCTO.

## Import The Mobile App

Open Android Studio, choose **Open**, and select:

```text
/Users/mohamedjames/Documents/SLID/border_management/reporting_app
```

Android Studio should detect the Gradle project and create the `app` run configuration. Let Gradle sync, then run it on an emulator or Android device.

Terminal checks:

```bash
cd reporting_app
JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" sh ./gradlew testDebugUnitTest
JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" sh ./gradlew assembleDebug
```

The debug APK is generated at:

```text
reporting_app/app/build/outputs/apk/debug/app-debug.apk
```

## Connect Mobile To Laravel

For emulator testing, use:

```text
http://10.0.2.2:8000/
```

For a real Android phone, use the computer or hosted server URL the phone can reach, for example:

```text
http://192.168.1.20:8000/
https://your-domain.example/
```

The Laravel admin can generate setup QR codes from `/admin/users`. The QR carries the server URL, officer email, temporary password, and optional device name.

## Demo Testing

Use `docs/onboarding/borderreach-demo-onboarding.md` for the demo onboarding flow and test logins.

Open `docs/onboarding/borderreach-demo-onboarding.html` in a browser for the comprehensive handoff guide.

Use `docs/testing/demo-end-to-end-test-guide.md` for the full web-to-Android QA script.

## Local Backend

```bash
cd webapp
composer install
npm install
php artisan migrate
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

For development login and mobile tests, use the accounts created by the Laravel seeders/admin setup described in `webapp/README.md`.
