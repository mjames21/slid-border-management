<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BorderReach is standardized border reporting software for offline field operations, synced review, maps, exports, and analysis.">
    <title>BorderReach | Standardized Border Reporting</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root {
            --ink: #1f2937;
            --title: #111827;
            --muted: #5b6b82;
            --line: #d9e3ee;
            --wash: #f4f7fb;
            --paper: #ffffff;
            --green: #08794f;
            --green-dark: #065f46;
            --green-soft: #e8f6ef;
            --blue: #2588c7;
            --navy: #263247;
            --shadow: 0 18px 48px rgba(20, 35, 55, 0.14);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        .shell {
            width: min(1160px, calc(100% - 40px));
            margin: 0 auto;
        }
        .site-nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 76px;
        }
        .brand img { width: 180px; height: auto; }
        .nav-links,
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 22px;
            font-size: 0.94rem;
            font-weight: 800;
            color: #263247;
        }
        .nav-actions { gap: 10px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            color: var(--title);
            font-weight: 850;
            cursor: pointer;
        }
        .btn.primary {
            border-color: var(--green);
            background: var(--green);
            color: #ffffff;
            box-shadow: 0 12px 28px rgba(8, 121, 79, 0.22);
        }
        .btn.primary:hover { background: var(--green-dark); }
        .btn.ghost {
            background: rgba(255, 255, 255, 0.13);
            border-color: rgba(255, 255, 255, 0.65);
            color: #ffffff;
        }
        .flash {
            width: min(1160px, calc(100% - 40px));
            margin: 18px auto 0;
            border-radius: 8px;
            padding: 13px 16px;
            font-weight: 760;
        }
        .flash.success { border: 1px solid #b9e5c4; background: #effaf2; color: #135c37; }
        .flash.error { border: 1px solid #fecaca; background: #fff1f2; color: #9f1239; }

        .hero {
            position: relative;
            min-height: 640px;
            color: #ffffff;
            background-image:
                linear-gradient(90deg, rgba(6, 27, 42, 0.82), rgba(6, 27, 42, 0.44) 48%, rgba(6, 27, 42, 0.12)),
                url("{{ asset('images/landing/borderreach-remote-border-road.png') }}");
            background-position: center;
            background-size: cover;
        }
        .hero .shell {
            min-height: 640px;
            display: flex;
            align-items: center;
        }
        .hero-copy {
            width: min(720px, 100%);
            padding: 74px 0;
        }
        .eyebrow {
            margin: 0 0 16px;
            color: #d8f5e6;
            font-size: 0.82rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            color: inherit;
            font-size: clamp(3.3rem, 7vw, 6.3rem);
            line-height: 0.95;
            letter-spacing: 0;
            font-weight: 950;
        }
        .hero-lede {
            margin: 22px 0 0;
            max-width: 650px;
            font-size: clamp(1.3rem, 2vw, 1.75rem);
            line-height: 1.24;
            font-weight: 850;
        }
        .hero-text {
            max-width: 620px;
            margin: 18px 0 0;
            color: #e4edf4;
            font-size: 1.08rem;
            font-weight: 560;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 32px;
        }

        .feature-strip {
            border-bottom: 1px solid var(--line);
            background: #ffffff;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0;
        }
        .feature {
            min-height: 126px;
            padding: 28px 24px;
            border-right: 1px solid var(--line);
        }
        .feature:last-child { border-right: 0; }
        .feature strong {
            display: block;
            color: var(--title);
            font-size: 1.02rem;
            font-weight: 900;
            line-height: 1.15;
        }
        .feature span {
            display: block;
            margin-top: 7px;
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 650;
        }

        .use-case {
            border-bottom: 1px solid var(--line);
            background: #ffffff;
            padding: 46px 0;
        }

        .use-case-inner {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 380px);
            gap: 28px;
            align-items: center;
        }

        .use-case-copy {
            max-width: 760px;
        }

        .use-case-copy .eyebrow {
            margin-bottom: 10px;
            color: var(--green);
        }

        .use-case-copy h2 {
            margin: 0;
            color: var(--title);
            font-size: clamp(1.7rem, 3vw, 2.5rem);
            font-weight: 940;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .use-case-copy p {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 1rem;
            font-weight: 590;
            line-height: 1.7;
        }

        .agency-card {
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--wash);
            padding: 18px;
        }

        .agency-card img {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border-radius: 8px;
            background: #ffffff;
            padding: 6px;
        }

        .agency-card strong {
            display: block;
            color: var(--title);
            font-weight: 920;
            line-height: 1.2;
        }

        .agency-card span {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 0.9rem;
            font-weight: 700;
        }

        section { padding: 74px 0; }
        .section-head {
            max-width: 760px;
            margin-bottom: 30px;
        }
        .section-head.center {
            margin-right: auto;
            margin-left: auto;
            text-align: center;
        }
        h2 {
            margin: 0;
            color: var(--title);
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1.02;
            font-weight: 950;
            letter-spacing: 0;
        }
        .section-head p {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 1.08rem;
            font-weight: 560;
        }
        .two-col {
            display: grid;
            grid-template-columns: minmax(0, 1.03fr) minmax(320px, 0.97fr);
            gap: 36px;
            align-items: center;
        }
        .photo-frame {
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            background: #edf3f7;
        }
        .photo-frame img {
            width: 100%;
            aspect-ratio: 16 / 10;
            object-fit: cover;
        }
        .check-list {
            display: grid;
            gap: 14px;
            margin-top: 26px;
        }
        .check {
            display: grid;
            grid-template-columns: 24px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            color: #344054;
            font-weight: 650;
        }
        .check::before {
            content: "";
            width: 18px;
            height: 18px;
            margin-top: 3px;
            border-radius: 999px;
            background: var(--green);
            box-shadow: inset 0 0 0 5px #d8f7e8;
        }

        .standards {
            background: var(--wash);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }
        .standard-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        .standard {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 22px;
        }
        .standard small {
            display: block;
            color: var(--green);
            font-size: 0.76rem;
            font-weight: 950;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }
        .standard strong {
            display: block;
            margin-top: 10px;
            color: var(--title);
            font-size: 1.12rem;
            font-weight: 930;
            line-height: 1.2;
        }
        .standard p {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 0.96rem;
            font-weight: 560;
        }

        .deployment {
            background: #ffffff;
        }
        .form-panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 28px;
            box-shadow: 0 12px 34px rgba(22, 35, 54, 0.08);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        label {
            display: block;
            color: #263247;
            font-size: 0.82rem;
            font-weight: 900;
        }
        input, select, textarea {
            width: 100%;
            min-height: 46px;
            margin-top: 7px;
            border: 1px solid #cbd8e6;
            border-radius: 8px;
            background: #ffffff;
            color: var(--title);
            padding: 11px 12px;
            font: inherit;
        }
        textarea { min-height: 118px; resize: vertical; }
        .span-2 { grid-column: 1 / -1; }
        .module-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }
        .module {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 46px;
            border: 1px solid #cbd8e6;
            border-radius: 8px;
            padding: 0 12px;
            font-weight: 800;
        }
        .module input {
            width: 16px;
            min-height: 16px;
            margin: 0;
            accent-color: var(--green);
        }
        .error-list {
            margin: 0 0 20px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff1f2;
            color: #9f1239;
            padding: 14px 16px;
            font-weight: 720;
        }

        .footer {
            border-top: 1px solid var(--line);
            background: #101827;
            color: #dbe7f2;
            padding: 30px 0;
        }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            font-size: 0.92rem;
            font-weight: 720;
        }
        .footer a { color: #d8f5e6; }

        @media (max-width: 980px) {
            .nav-links { display: none; }
            .hero, .hero .shell { min-height: 560px; }
            .feature-grid, .standard-grid, .two-col, .use-case-inner { grid-template-columns: 1fr; }
            .feature { border-right: 0; border-bottom: 1px solid var(--line); }
            .feature:last-child { border-bottom: 0; }
        }
        @media (max-width: 700px) {
            .shell, .flash { width: min(100% - 28px, 1160px); }
            .nav-inner { min-height: 68px; }
            .brand img { width: 152px; }
            .hero { background-position: center right; }
            .hero .shell { align-items: end; }
            .hero-copy { padding: 54px 0; }
            section { padding: 54px 0; }
            .form-panel { padding: 18px; }
            .form-grid, .module-row { grid-template-columns: 1fr; }
            .footer-inner { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <nav class="site-nav" aria-label="Primary navigation">
        <div class="shell nav-inner">
            <a class="brand" href="{{ route('welcome') }}" aria-label="BorderReach home">
                <img src="{{ asset('images/borderreach-logo.svg') }}" alt="BorderReach">
            </a>
            <div class="nav-links" aria-label="Page sections">
                <a href="#features">Features</a>
                <a href="#standards">Standards</a>
                <a href="#deployment">Deployment</a>
            </div>
            <div class="nav-actions">
                <a class="btn primary" href="#deployment">Request review</a>
            </div>
        </div>
    </nav>

    @if (session('status'))
        <div class="flash success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash error">Please review the deployment request fields and try again.</div>
    @endif

    <main>
        <header class="hero">
            <div class="shell">
                <div class="hero-copy">
                    <p class="eyebrow">Offline field reporting for border operations</p>
                    <h1>BorderReach</h1>
                    <p class="hero-lede">Standardized border reporting that keeps working offline.</p>
                    <p class="hero-text">
                        Report immigration, customs, health, and security activity from remote border posts, then sync clean records for review, maps, exports, and analysis when connectivity returns.
                    </p>
                    <div class="hero-actions">
                        <a class="btn primary" href="#deployment">Request deployment review</a>
                        <a class="btn ghost" href="#standards">View standards</a>
                    </div>
                </div>
            </div>
        </header>

        <section id="features" class="feature-strip" aria-label="Core capabilities">
            <div class="shell feature-grid">
                <div class="feature">
                    <strong>Offline reporting</strong>
                    <span>Capture first. Sync when a network is available.</span>
                </div>
                <div class="feature">
                    <strong>Standardized forms</strong>
                    <span>Use controlled templates for border workflows.</span>
                </div>
                <div class="feature">
                    <strong>Location aware</strong>
                    <span>Attach post, device, GPS, and map context.</span>
                </div>
                <div class="feature">
                    <strong>Review and export</strong>
                    <span>Search, validate, map, export, and push records.</span>
                </div>
            </div>
        </section>

        <section class="use-case" aria-label="Sierra Leone Immigration Service use case">
            <div class="shell use-case-inner">
                <div class="use-case-copy">
                    <p class="eyebrow">Operational use case</p>
                    <h2>Used by Sierra Leone Immigration Service for border management.</h2>
                    <p>
                        Sierra Leone Immigration Service is using BorderReach to support standardized border reporting,
                        officer assignment, GPS context, and synced operational review across border posts.
                    </p>
                </div>
                <div class="agency-card">
                    <img src="{{ asset('images/slid-logo.png') }}" alt="Sierra Leone Immigration Service logo">
                    <div>
                        <strong>Sierra Leone Immigration Service</strong>
                        <span>Border management deployment</span>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="shell two-col">
                <div class="photo-frame">
                    <img src="{{ asset('images/landing/borderreach-customs-post.png') }}" alt="Remote border post with officers at a checkpoint">
                </div>
                <div>
                    <div class="section-head">
                        <h2>Built for posts that still need national visibility.</h2>
                        <p>
                            BorderReach gives agencies a practical way to manage reports from remote corridors without waiting for perfect infrastructure.
                        </p>
                    </div>
                    <div class="check-list">
                        <div class="check">One workspace for immigration, customs, health, and security reporting.</div>
                        <div class="check">Controlled project versions so field forms can be updated and redeployed.</div>
                        <div class="check">Officer assignments by post, module, and project access.</div>
                        <div class="check">API and export paths for integration with national systems.</div>
                    </div>
                </div>
            </div>
        </section>

        <section id="standards" class="standards">
            <div class="shell">
                <div class="section-head center">
                    <h2>Standards-backed reporting templates.</h2>
                    <p>Start from proven international reporting structures, then adapt the fields to the agency workflow.</p>
                </div>
                <div class="standard-grid">
                    <article class="standard">
                        <small>Immigration</small>
                        <strong>ICAO Doc 9303</strong>
                        <p>Travel document inspection fields for MRZ, VIZ identity data, document validity, and officer decisions.</p>
                    </article>
                    <article class="standard">
                        <small>Customs</small>
                        <strong>WCO Data Model</strong>
                        <p>Declaration and inspection fields for goods, cargo movement, duties, seizures, and control outcomes.</p>
                    </article>
                    <article class="standard">
                        <small>Health</small>
                        <strong>WHO IHR</strong>
                        <p>Point-of-entry screening fields for symptoms, exposure risk, referrals, isolation, and follow-up action.</p>
                    </article>
                    <article class="standard">
                        <small>Security</small>
                        <strong>Incident reporting</strong>
                        <p>Structured reporting for incident type, severity, evidence, agency notification, and resolution status.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="deployment" class="deployment">
            <div class="shell two-col">
                <div class="section-head">
                    <h2>Request a deployment review.</h2>
                    <p>
                        Share the country, agency, expected scope, and preferred environment. The BorderReach team will prepare the right setup path.
                    </p>
                    <p>
                        Email: <a href="mailto:border.reach.onboarding@memeh.org">border.reach.onboarding@memeh.org</a>
                    </p>
                </div>
                <form class="form-panel" method="POST" action="{{ route('deployment-requests.store') }}">
                    @csrf
                    @if ($errors->any())
                        <div class="error-list">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <div class="form-grid">
                        <label>
                            Country
                            <input name="country_name" value="{{ old('country_name') }}" required autocomplete="country-name">
                        </label>
                        <label>
                            Agency
                            <input name="agency_name" value="{{ old('agency_name') }}" required>
                        </label>
                        <label>
                            Contact name
                            <input name="contact_name" value="{{ old('contact_name') }}" required autocomplete="name">
                        </label>
                        <label>
                            Work email
                            <input type="email" name="contact_email" value="{{ old('contact_email') }}" required autocomplete="email">
                        </label>
                        <label>
                            Phone
                            <input name="contact_phone" value="{{ old('contact_phone') }}" autocomplete="tel">
                        </label>
                        <label>
                            Role
                            <input name="contact_role" value="{{ old('contact_role') }}">
                        </label>
                        <label>
                            Deployment scale
                            <select name="deployment_plan">
                                @foreach (\App\Models\Country::deploymentPlanLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('deployment_plan', 'program') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Environment
                            <select name="deployment_type">
                                @foreach (\App\Models\Country::deploymentTypeLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('deployment_type', 'managed') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <fieldset class="span-2" style="border:0;padding:0;margin:0;">
                            <legend style="color:#263247;font-size:.82rem;font-weight:900;">Reporting modules</legend>
                            <div class="module-row">
                                @foreach (['immigration' => 'Immigration', 'customs' => 'Customs', 'health' => 'Health', 'security' => 'Security'] as $value => $label)
                                    <label class="module">
                                        <input type="checkbox" name="modules[]" value="{{ $value }}" @checked(in_array($value, old('modules', ['immigration']), true))>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="span-2">
                            Deployment notes
                            <textarea name="message">{{ old('message') }}</textarea>
                        </label>
                    </div>
                    <button class="btn primary" type="submit" style="margin-top:18px;">Send request</button>
                </form>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="shell footer-inner">
            <span>BorderReach. Standardized border reporting for difficult operating environments.</span>
            <a href="mailto:border.reach.onboarding@memeh.org">border.reach.onboarding@memeh.org</a>
        </div>
    </footer>
</body>
</html>
