# BorderReach Demo Onboarding Guide

Use this document to onboard a reviewer, tester, or pilot user into the local BorderReach demo.

These accounts are for local/demo testing only. Do not use these passwords on a hosted, staging, or production system.

## Demo Accounts

| User | Surface | Email | Password | Role | Assignment |
| --- | --- | --- | --- | --- | --- |
| Platform admin | Web admin | `admin@slid.local` | `Password123!` | HQ admin | Country tenant `SLE` |
| Border officer | Mobile app | `officer@slid.local` | `Officer123!` | Border officer | Bendu border post, `BEN-LND` |

Officer test assignment:

```text
Country: Sierra Leone demo tenant
Border post: Bendu
Border post code: BEN-LND
Digital address: SLE-BP-BEN-LND
```

## 1. Start The Backend

From the Laravel app:

```bash
cd webapp
composer install
npm install
/opt/homebrew/opt/php@8.4/bin/php artisan migrate --seed
npm run build
/opt/homebrew/opt/php@8.4/bin/php artisan serve --host=0.0.0.0 --port=8000
```

Open the web admin:

```text
http://127.0.0.1:8000/login
```

Log in with:

```text
Email: admin@slid.local
Password: Password123!
```

## 2. Admin Smoke Test

After logging in as the admin, confirm these pages open:

```text
/admin/dashboard
/admin/forms
/admin/submissions
/admin/users
/admin/border-posts
/admin/countries
```

Expected result:

- The admin dashboard loads.
- A published form is available for mobile sync.
- Bendu `BEN-LND` exists as an active border post.
- `officer@slid.local` exists, is active, and is assigned to Bendu.

## 3. Connect The Mobile App

Open the Android project:

```text
/Users/mohamedjames/Documents/SLID/border_management/reporting_app
```

Use this server URL on the mobile login screen:

| Device | Server URL |
| --- | --- |
| Android emulator | `http://10.0.2.2:8000/` |
| Real phone on same Wi-Fi | `http://<your-computer-lan-ip>:8000/` |
| Hosted test server | `https://<your-domain>/` |

For a real phone, the phone and computer must be on the same network, and Laravel must be running with:

```text
--host=0.0.0.0 --port=8000
```

Mobile login:

```text
Email: officer@slid.local
Password: Officer123!
```

Expected result:

- The officer signs in.
- The app downloads active forms.
- The app shows the Bendu border post assignment.

## 4. QR Setup Flow

Use this when you want the officer to scan setup details instead of typing them.

1. Log in to the web admin as `admin@slid.local`.
2. Go to `/admin/users`.
3. Open the setup QR page for `officer@slid.local`.
4. Enter the server URL the phone can reach.
5. Generate the setup QR.
6. On Android, tap **Scan Setup QR**.
7. Scan the QR code.
8. Confirm the server URL, email, and device name are filled.
9. Sign in.

Important: generating a setup QR can reset the officer password to a temporary password inside the QR. Use the newest QR, or reseed the local database to return to `Officer123!`.

## 5. End-To-End Test

1. Sign in to Android as `officer@slid.local`.
2. Open a downloaded form.
3. Confirm the form is split into steps.
4. Fill the required fields.
5. Use date/time pickers where available.
6. Use MRZ scan fields when testing travel-document reporting.
7. Save a draft and reopen it.
8. Finalize and send.
9. Turn off network and submit another report.
10. Restore network and sync.
11. In the web admin, open `/admin/submissions`.
12. Confirm the report appears and the answers are visible in a table.

Expected result:

- Online reports sync immediately.
- Offline reports save locally and clearly show pending status.
- Pending reports retry when connectivity returns.
- The web admin shows the submitted answers, officer, device, border post, digital address, and location metadata when available.

## 6. Common Issues

| Issue | Cause | Fix |
| --- | --- | --- |
| Phone cannot reach `127.0.0.1` | That address points to the phone itself | Use `10.0.2.2` for emulator or the computer LAN IP for a real phone |
| Phone cannot reach LAN IP | Laravel is bound to localhost | Start Laravel with `--host=0.0.0.0 --port=8000` |
| Login works on web but not mobile | Wrong server URL or stale QR password | Verify the URL and use the newest QR or `Officer123!` after reseeding |
| No forms appear on mobile | No published form for the tenant | Publish a form in `/admin/forms` |
| Submission stays pending | Server unreachable or token expired | Check URL/network, sign in again, then sync |

## 7. Full QA Guide

For the complete test script, use:

```text
docs/testing/demo-end-to-end-test-guide.md
```
