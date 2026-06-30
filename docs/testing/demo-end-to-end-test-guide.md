# BorderReach Demo End-to-End Test Guide

Use this guide to test the Laravel admin console and Android field app together with local demo accounts.

These credentials are for local/demo testing only. Do not use them on a hosted or production system.

## Demo Logins

| Surface | URL or screen | Email | Password | Role | Assignment |
| --- | --- | --- | --- | --- | --- |
| Web admin | `/login` or `/admin/dashboard` | `admin@slid.local` | `Password123!` | HQ admin | Tenant `SLE` |
| Android mobile | Login screen | `officer@slid.local` | `Officer123!` | Border officer | Bendu, `BEN-LND`, digital address `SLE-BP-BEN-LND` |

The non-production Laravel seeder creates these accounts. Passwords can be overridden locally with `SEED_DEMO_ADMIN_PASSWORD` and `SEED_DEMO_OFFICER_PASSWORD`.

## 1. Prepare The Backend

From the Laravel app folder:

```bash
cd webapp
composer install
npm install
/opt/homebrew/opt/php@8.4/bin/php artisan migrate --seed
npm run build
```

If you need a clean local demo database and there is no important local data, reset and seed:

```bash
/opt/homebrew/opt/php@8.4/bin/php artisan migrate:fresh --seed
```

Start the backend for browser and mobile testing:

```bash
/opt/homebrew/opt/php@8.4/bin/php artisan serve --host=0.0.0.0 --port=8000
```

Use `--host=0.0.0.0` when testing with a real Android phone so the phone can reach your computer over Wi-Fi.

## 2. Web Admin Smoke Test

1. Open `http://127.0.0.1:8000/login`.
2. Log in with `admin@slid.local` / `Password123!`.
3. Confirm the admin navigation loads.
4. Visit `/admin/dashboard`.
5. Visit `/admin/forms`.
6. Open or build a form and confirm a published version exists for mobile sync.
7. Visit `/admin/border-posts` and confirm Bendu `BEN-LND` exists with digital address `SLE-BP-BEN-LND`.
8. Visit `/admin/users` and confirm `officer@slid.local` is active and assigned to Bendu.

Expected result: the web admin can review tenants, forms, border posts, users, submissions, and dashboard views without errors.

## 3. Android Connection Values

Use one of these server URLs on the Android login screen:

| Test device | Server URL |
| --- | --- |
| Android emulator | `http://10.0.2.2:8000/` |
| Real Android phone on same Wi-Fi | `http://<your-computer-lan-ip>:8000/` |
| Hosted test server | `https://<your-domain>/` |

For a real phone, find the LAN IP from your computer network settings. The phone must be on the same network and the Laravel server must be running with `--host=0.0.0.0`.

## 4. Android Login Test

1. Open the Android project in Android Studio from `reporting_app`.
2. Run the `app` configuration on an emulator or device.
3. On the login screen, enter the server URL.
4. Enter `officer@slid.local`.
5. Enter `Officer123!`.
6. Keep the generated device name or enter a test name such as `demo-device-01`.
7. Tap **Sign In**.

Expected result: the officer signs in, receives the Bendu border-post assignment, and downloads active published forms.

## 5. QR Setup Test

1. Log in to the web admin as `admin@slid.local`.
2. Go to `/admin/users`.
3. Open setup QR for `officer@slid.local`.
4. Enter the server URL the phone can reach.
5. Generate the setup QR.
6. On Android, tap **Scan Setup QR** and scan it.
7. Confirm the server URL, email, and device name are filled.
8. Sign in.

Note: generating a setup QR resets the officer password to a temporary password encoded in the QR. If you want to use the static demo password again, reseed locally or set the user password back from the admin workflow.

## 6. Form Capture And Offline Test

1. In Android, open the downloaded form.
2. Confirm the form is split into step screens, not one long page.
3. Fill required fields.
4. Use date/time pickers where shown.
5. Tap **Scan Passport MRZ** if you are testing travel-document fields.
6. Save a draft, reopen it, and confirm values remain.
7. Finalize the report.
8. Turn off network and finalize another report.

Expected result: completed reports are saved locally. If the server is unreachable, the app clearly says the submission is saved but not sent and will retry when connectivity returns.

## 7. Sync Verification

1. Restore device connectivity.
2. Tap manual sync or wait for background sync.
3. Confirm the mobile app marks accepted submissions as synced.
4. In the web admin, open `/admin/submissions`.
5. Open the latest submission.
6. Confirm the answer table shows the form answers as rows.
7. Confirm custody metadata exists: officer, device, border post, digital address, tenant, and GPS fields when permission was granted.
8. Open `/admin/dashboard` and confirm the submission appears in aggregates or map panels.

Expected result: the server returns a receipt, the phone stores the accepted status, and the web admin can review/export the report.

## 8. Useful Test Commands

Laravel tests:

```bash
cd webapp
/opt/homebrew/opt/php@8.4/bin/php artisan test
```

Android tests:

```bash
cd reporting_app
JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" sh ./gradlew testDebugUnitTest
```

Debug APK:

```bash
cd reporting_app
JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home" sh ./gradlew assembleDebug
```

APK output:

```text
reporting_app/app/build/outputs/apk/debug/app-debug.apk
```

## 9. Common Issues

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Phone cannot connect to `127.0.0.1` | `127.0.0.1` points to the phone itself | Use `10.0.2.2` on emulator or the computer LAN IP on real phone |
| Phone cannot connect to LAN IP | Laravel is bound only to localhost | Start Laravel with `--host=0.0.0.0 --port=8000` |
| Login says user is unassigned | User has no active border post | Assign the officer to an active border post in `/admin/users` |
| No forms appear on Android | No published form for officer tenant | Publish a form in `/admin/forms` |
| Submission stays pending | Server unreachable or auth token expired | Check server URL, network, login again, then sync |
| QR login fails after generating multiple QRs | Latest QR reset the password again | Use the newest QR or reset the officer password |

