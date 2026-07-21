# BorderReach Shared Hosting Welcome Page

This folder contains standalone BorderReach welcome pages for basic shared hosting.

- `index.php` is the recommended SiteGround version when PHP `mail()` is enabled.
- `index.html` is a static Tailwind version for brochure-only hosting or external form handling.

## Deploy

1. Upload the contents of this folder to the web root or target public folder on the shared host.
2. Keep `assets/` next to the index file you use.
3. Visit the page in a browser.

For SiteGround, upload these files into the target `public_html` folder or a subfolder such as `public_html/borderreach`:

- `index.php`
- `index.html` if you want the static Tailwind version instead
- `assets/`

If both `index.php` and `index.html` exist, most PHP hosting setups load `index.php` first. Rename or remove the one you are not using if your host chooses the wrong page.

## Contact Form

Both versions use this onboarding address:

`onboarding@borderreach.memeh.org`

The PHP page sends deployment requests from the server. The recipient is configured near the top of `index.php`:

```php
$to = 'onboarding@borderreach.memeh.org';
```

The host must support PHP `mail()` or a working sendmail/SMTP bridge. If mail is disabled on the host, the page will show an error and the requester can email the address directly.

The static Tailwind page uses a standard email handoff:

```html
mailto:onboarding@borderreach.memeh.org
```

Use `index.html` when the site is only a brochure page, and use `index.php` when deployment requests should submit through SiteGround.

## Branding

Replace these files to change the landing images and public agency proof point without editing code:

- `assets/borderreach-remote-border-road.png`
- `assets/borderreach-customs-post.png`
- `assets/sierra-leone-immigration-logo.png`

The public page intentionally does not expose an application sign-in link. Operational access stays inside the deployed BorderReach environment.

## Tailwind Version

`index.html` uses the Tailwind CDN:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

That is good for a quick shared-hosting page. For stricter production hardening, compile Tailwind into a local CSS file and remove the CDN script.
