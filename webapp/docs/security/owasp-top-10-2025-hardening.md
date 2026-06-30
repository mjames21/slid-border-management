# OWASP Top 10:2025 Hardening Notes

Source lens: https://owasp.org/Top10/2025/

This file maps the Laravel backend controls to the current OWASP Top 10:2025 categories. Keep it current when changing auth, file uploads, sync, exports, or deployment configuration.

| OWASP category | Current controls in this app | Follow-up discipline |
| --- | --- | --- |
| A01 Broken Access Control | Admin routes require auth plus active admin status. Mobile APIs require Sanctum tokens, device ownership, active user status, and narrow token abilities for config/sync. Dashboard views are scoped by owner. | Add policy classes before introducing tenant-wide delegated roles. |
| A02 Security Misconfiguration | Production env template disables debug, enables HTTPS URL generation, trusted hosts/proxies, encrypted secure cookies, warning-level logs, and cache-ready deploy commands. Web root must be `public/`. | On each deployment, verify `.env` matches `.env.production.example` and the reverse proxy forwards HTTPS correctly. |
| A03 Software Supply Chain Failures | Composer platform is pinned to PHP `>=8.4.1`; `composer.lock` is committed; deployment uses `composer install --no-dev --optimize-autoloader`. | Run `composer audit` and `npm audit` in CI before release. |
| A04 Cryptographic Failures | Laravel app key, encrypted sessions in production/local hardened env, HTTPS-only production cookies, HSTS on HTTPS, hashed passwords, Fortify 2FA/passkeys available. | Never reuse `.env` keys between environments. Rotate keys using `APP_PREVIOUS_KEYS` when needed. |
| A05 Injection | Eloquent query builder is used for user filters; dashboard filters are server-side whitelisted; form IDs and device IDs have strict regex rules; CSV exports neutralize spreadsheet formulas. | Keep raw SQL limited to fixed internal aggregate expressions only. |
| A06 Insecure Design | Mobile workflow binds submissions to user, device, border post, country, and form version; QR setup rotates credentials and removes old tokens; offline sync returns accepted/rejected IDs. | Add explicit threat models before adding SMS reporting, MRZ capture, or multi-tenant billing. |
| A07 Authentication Failures | Fortify login is rate limited and rejects inactive accounts; mobile login is throttled and rotates same-device tokens; public registration is disabled. | Require 2FA for HQ admins before production go-live. |
| A08 Software or Data Integrity Failures | Published form versions are immutable sync targets; QR setup payloads are generated server-side; uploaded boundary ZIP files are bounded and path-checked. | Sign mobile releases and keep release HTTP cleartext disabled. |
| A09 Security Logging and Alerting Failures | Audit events record mobile login/sync, admin imports, publishes, exports, QR setup, country branding, and border-post changes with IP and user agent. | Forward Laravel logs and audit event summaries to central monitoring before live field operations. |
| A10 Mishandling of Exceptional Conditions | API paths render JSON even without an Accept header; request exception text is truncated; secrets are not flashed back to sessions; debug is off in production and local hardened env. | Add alert thresholds for repeated 401/403/422/500 responses. |

