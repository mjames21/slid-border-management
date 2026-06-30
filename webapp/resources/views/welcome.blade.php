<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BorderReach is offline-first single-window border reporting software for remote immigration, customs, health, and security oversight.">
    <title>BorderReach | Border Reporting Software</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root {
            --ink: #243044;
            --title: #182236;
            --muted: #5e6d82;
            --line: #dce6ef;
            --wash: #f5f8fb;
            --green: #08794f;
            --green-soft: #e8f6ef;
            --blue: #2588c7;
            --navy: #123f59;
            --paper: #ffffff;
            --shadow: 0 22px 60px rgba(31, 48, 74, 0.12);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        .page { overflow: hidden; }
        .shell {
            width: min(1180px, calc(100% - 44px));
            margin: 0 auto;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            min-height: 84px;
            border-bottom: 1px solid var(--line);
            background: #ffffff;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            min-width: auto;
        }
        .brand img { width: 176px; height: auto; }
        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            color: #253247;
            font-size: 0.95rem;
            font-weight: 760;
        }
        .nav-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-width: auto;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            color: var(--title);
            padding: 0 18px;
            font-weight: 850;
        }
        .btn.primary {
            border-color: var(--green);
            background: var(--green);
            color: #ffffff;
            box-shadow: 0 12px 28px rgba(8, 121, 79, 0.18);
        }
        .btn.blue {
            border-color: var(--blue);
            background: var(--blue);
            color: #ffffff;
        }

        .flash {
            width: min(1180px, calc(100% - 44px));
            margin: 18px auto 0;
            border-radius: 8px;
            padding: 13px 16px;
            font-weight: 760;
        }
        .flash.success { border: 1px solid #b9e5c4; background: #effaf2; color: #135c37; }
        .flash.error { border: 1px solid #fecaca; background: #fff1f2; color: #9f1239; }

        .hero {
            padding: 88px 0 64px;
            background: #ffffff;
            text-align: center;
        }
        .hero h1 {
            max-width: min(1040px, 100%);
            margin: 0 auto;
            color: var(--title);
            font-size: 5.15rem;
            font-weight: 900;
            letter-spacing: 0;
            line-height: 0.98;
            overflow-wrap: anywhere;
        }
        .hero p {
            max-width: 720px;
            margin: 26px auto 0;
            color: var(--muted);
            font-size: 1.22rem;
            font-weight: 520;
        }
        .hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 34px;
        }
        .standard-strip {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            width: min(1040px, 100%);
            margin-top: 24px;
            margin-right: auto;
            margin-left: auto;
        }
        .standard-card {
            border: 1px solid #d6e4ee;
            border-radius: 8px;
            background: #ffffff;
            padding: 12px 14px;
            text-align: left;
        }
        .standard-card strong,
        .standard-card span,
        .standard-card small {
            display: block;
        }
        .standard-card strong {
            color: var(--title);
            font-size: 0.92rem;
            font-weight: 900;
            line-height: 1.15;
        }
        .standard-card span {
            margin-top: 5px;
            color: var(--accent);
            font-size: 0.76rem;
            font-weight: 900;
        }
        .standard-card small {
            margin-top: 7px;
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .product-stage {
            position: relative;
            width: min(1040px, 100%);
            margin: 46px auto 0;
        }
        .desktop-preview {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: var(--shadow);
            text-align: left;
        }
        .preview-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--line);
            background: #ffffff;
            padding: 14px 18px;
            color: var(--title);
            font-size: 0.88rem;
            font-weight: 900;
        }
        .window-dots {
            display: flex;
            gap: 6px;
        }
        .window-dots span {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #cdd8e4;
        }
        .preview-body {
            display: grid;
            grid-template-columns: 188px minmax(0, 1fr);
            min-height: 430px;
        }
        .preview-side {
            background: #152d43;
            color: #dbeef8;
            padding: 20px 16px;
        }
        .preview-side strong {
            display: block;
            margin-bottom: 18px;
            color: #ffffff;
            font-size: 0.92rem;
        }
        .preview-side span {
            display: block;
            margin-bottom: 8px;
            border-radius: 6px;
            padding: 9px 10px;
            background: rgba(255, 255, 255, 0.08);
            font-size: 0.78rem;
            font-weight: 780;
        }
        .preview-main {
            background: #f7fafc;
            padding: 18px;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .metric {
            border: 1px solid #dfe8f0;
            border-radius: 8px;
            background: #ffffff;
            padding: 13px;
        }
        .metric span {
            display: block;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 850;
        }
        .metric strong {
            display: block;
            margin-top: 5px;
            color: var(--title);
            font-size: 1.45rem;
            line-height: 1;
        }
        .preview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 14px;
        }
        .mini-panel {
            border: 1px solid #dfe8f0;
            border-radius: 8px;
            background: #ffffff;
            padding: 14px;
        }
        .mini-panel strong {
            display: block;
            color: var(--title);
            font-size: 0.86rem;
        }
        .bars {
            display: flex;
            align-items: end;
            gap: 8px;
            height: 126px;
            margin-top: 16px;
            border-bottom: 1px solid #cbd7e3;
        }
        .bars span {
            flex: 1;
            min-height: 12px;
            border-radius: 4px 4px 0 0;
            background: var(--blue);
        }
        .map-panel {
            position: relative;
            height: 142px;
            margin-top: 12px;
            overflow: hidden;
            border-radius: 7px;
            background:
                radial-gradient(circle at 34% 68%, #31b86f 0 11px, transparent 12px),
                radial-gradient(circle at 70% 40%, #efb43b 0 9px, transparent 10px),
                radial-gradient(circle at 58% 76%, #e85d5d 0 8px, transparent 9px),
                linear-gradient(145deg, #e5f7eb, #dceefa);
        }
        .map-panel::before {
            content: "";
            position: absolute;
            inset: 19px;
            border: 2px dashed rgba(18, 63, 89, 0.42);
            border-radius: 44% 56% 48% 52%;
        }
        .records {
            grid-column: 1 / -1;
        }
        .record-line {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            border-bottom: 1px solid #e8eef4;
            padding: 10px 0;
            color: #536175;
            font-size: 0.83rem;
            font-weight: 760;
        }
        .record-line:last-child { border-bottom: 0; }
        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            border-radius: 999px;
            background: #e5f7ec;
            color: #19683e;
            padding: 0 9px;
            font-size: 0.72rem;
            font-weight: 850;
        }
        .phone-preview {
            position: absolute;
            right: -18px;
            bottom: -32px;
            width: 230px;
            overflow: hidden;
            border: 10px solid #172033;
            border-radius: 30px;
            background: #ffffff;
            box-shadow: 0 22px 44px rgba(31, 48, 74, 0.24);
            text-align: left;
        }
        .phone-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e3ebf2;
            padding: 12px 14px;
            color: var(--title);
            font-size: 0.72rem;
            font-weight: 900;
        }
        .phone-body { padding: 14px; }
        .step-dots {
            display: flex;
            gap: 4px;
            margin-bottom: 12px;
        }
        .step-dots span {
            width: 18px;
            height: 4px;
            border-radius: 999px;
            background: #d7e3ec;
        }
        .step-dots .active { background: var(--green); }
        .phone-field {
            margin-bottom: 8px;
            border: 1px solid #d8e2eb;
            border-radius: 7px;
            padding: 8px;
            color: #526173;
            font-size: 0.68rem;
            font-weight: 760;
        }
        .phone-button {
            display: grid;
            place-items: center;
            min-height: 28px;
            border-radius: 7px;
            background: var(--green);
            color: #ffffff;
            font-size: 0.68rem;
            font-weight: 900;
        }

        .stats {
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            background: #ffffff;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }
        .stat {
            border-right: 1px solid var(--line);
            padding: 34px 24px;
            text-align: center;
        }
        .stat:last-child { border-right: 0; }
        .stat strong {
            display: block;
            color: var(--blue);
            font-size: 3rem;
            line-height: 1;
        }
        .stat span {
            display: block;
            margin-top: 8px;
            color: var(--muted);
            font-weight: 760;
        }

        section { padding: 92px 0; }
        .section-head {
            max-width: 850px;
            margin-bottom: 36px;
        }
        .section-head.center {
            margin-right: auto;
            margin-left: auto;
            text-align: center;
        }
        .section-head h2 {
            margin: 0;
            color: var(--title);
            font-size: 4rem;
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1;
        }
        .section-head p {
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 1.08rem;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }
        .feature {
            min-height: 270px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 24px;
        }
        .feature-icon {
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green);
            font-weight: 900;
        }
        .feature h3 {
            margin: 22px 0 0;
            color: var(--title);
            font-size: 1.18rem;
        }
        .feature p {
            margin: 10px 0 0;
            color: var(--muted);
        }

        .solution-section { background: var(--wash); }
        .solution-block {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 44px;
            align-items: start;
        }
        .capability-list {
            display: grid;
            gap: 12px;
        }
        .capability {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 18px 20px;
        }
        .capability strong {
            display: block;
            color: var(--title);
            font-size: 1rem;
        }
        .capability span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
        }

        .workflow {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
        }
        .workflow-step {
            min-height: 210px;
            border-right: 1px solid var(--line);
            padding: 24px;
        }
        .workflow-step:last-child { border-right: 0; }
        .workflow-step span {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green);
            font-weight: 900;
        }
        .workflow-step h3 {
            margin: 18px 0 0;
            color: var(--title);
            font-size: 1.1rem;
        }
        .workflow-step p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .cta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }
        .cta-card {
            min-height: 300px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 30px;
        }
        .cta-card h2 {
            margin: 0;
            color: var(--title);
            font-size: 2.7rem;
            line-height: 1;
        }
        .cta-card p {
            color: var(--muted);
            font-size: 1.02rem;
        }
        .request-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 18px;
        }
        .request-form label {
            display: block;
            margin-bottom: 5px;
            color: #314156;
            font-size: 0.78rem;
            font-weight: 850;
        }
        .request-form input:not([type="checkbox"]),
        .request-form select,
        .request-form textarea {
            width: 100%;
            border: 1px solid #cbd7e3;
            border-radius: 8px;
            background: #ffffff;
            color: var(--ink);
            padding: 10px 11px;
            font: inherit;
        }
        .request-form textarea { min-height: 96px; resize: vertical; }
        .request-form .full { grid-column: 1 / -1; }

        footer {
            border-top: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            padding: 36px 0;
            font-size: 0.94rem;
        }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
        }

        @media (max-width: 1040px) {
            .nav-links { display: none; }
            .hero h1 {
                font-size: 4.1rem;
            }
            .section-head h2 { font-size: 3rem; }
            .feature-grid,
            .workflow,
            .stats-grid,
            .standard-strip {
                grid-template-columns: repeat(2, 1fr);
            }
            .solution-block,
            .cta-grid,
            .preview-body {
                grid-template-columns: 1fr;
            }
            .phone-preview {
                position: static;
                width: min(260px, calc(100% - 32px));
                margin: 20px auto 24px;
            }
            .preview-side {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            .preview-side strong { grid-column: 1 / -1; margin-bottom: 4px; }
            .preview-side span { margin-bottom: 0; }
            .workflow-step:nth-child(2n),
            .stat:nth-child(2n) { border-right: 0; }
            .workflow-step,
            .stat { border-bottom: 1px solid var(--line); }
        }
        @media (max-width: 680px) {
            .shell { width: min(100% - 28px, 1180px); }
            .nav {
                gap: 12px;
                min-height: 74px;
            }
            .brand img { width: 148px; }
            .nav-actions { display: none; }
            .hero { padding: 66px 0 46px; }
            .hero h1 {
                max-width: 100%;
                font-size: 2.25rem;
                line-height: 1.05;
                overflow-wrap: anywhere;
            }
            .hero p {
                max-width: 100%;
                font-size: 0.96rem;
                overflow-wrap: anywhere;
            }
            .hero-actions .btn {
                width: 100%;
                max-width: 280px;
            }
            .standard-strip { grid-template-columns: 1fr; }
            .product-stage { margin-top: 52px; }
            .section-head h2 { font-size: 2.15rem; }
            .stat strong { font-size: 2.05rem; }
            .cta-card h2 { font-size: 1.9rem; }
            .desktop-preview { max-width: 100%; }
            .preview-top {
                justify-content: center;
                flex-wrap: wrap;
                text-align: center;
            }
            .preview-top > span { width: 100%; }
            .preview-side { grid-template-columns: 1fr 1fr; }
            .metrics,
            .preview-grid,
            .feature-grid,
            .workflow,
            .stats-grid,
            .request-form {
                grid-template-columns: 1fr;
            }
            .records { grid-column: auto; }
            .stat,
            .workflow-step { border-right: 0; }
            section { padding: 62px 0; }
            .cta-card { padding: 22px; }
            .footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <header>
        <div class="shell nav">
            <a class="brand" href="{{ url('/') }}" aria-label="BorderReach home">
                <img src="{{ asset('images/borderreach-logo.svg') }}" alt="BorderReach">
            </a>
            <nav class="nav-links" aria-label="Primary navigation">
                <a href="#features">Features</a>
                <a href="#solutions">Services</a>
                <a href="#deployment">Enterprise</a>
                <a href="#deployment">Pricing</a>
                <a href="#standards">Resources</a>
                <a href="#deployment">Contact</a>
            </nav>
            <div class="nav-actions">
                <a class="btn primary" href="{{ route('get-started') }}">Get started</a>
            </div>
        </div>

        @if(session('status'))
            <div class="flash success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="flash error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="hero">
            <div class="shell">
                <h1>Make every remote border visible.</h1>
                <p>
                    BorderReach gives governments a single window to report, supervise, and analyze hard-to-reach border posts with the same discipline as class A crossings, even when connectivity is unreliable.
                </p>
                <div class="hero-actions">
                    <a class="btn blue" href="#features">Explore Product</a>
                    <a class="btn" href="{{ route('get-started') }}">Request deployment review</a>
                </div>
                <div class="standard-strip" aria-label="Supported report types and standards">
                    <div class="standard-card">
                        <strong>Travel documents</strong>
                        <span>ICAO Doc 9303</span>
                        <small>Passport, visa, MRZ, VIZ, and document-security checks.</small>
                    </div>
                    <div class="standard-card">
                        <strong>Customs reports</strong>
                        <span>WCO Data Model</span>
                        <small>Goods, transport, HS code, duty, seizure, and referral fields.</small>
                    </div>
                    <div class="standard-card">
                        <strong>Health screening</strong>
                        <span>WHO IHR</span>
                        <small>Point-of-entry symptoms, exposure risk, referral, and notification.</small>
                    </div>
                    <div class="standard-card">
                        <strong>Security incidents</strong>
                        <span>Border incident baseline</span>
                        <small>Irregular crossings, smuggling, evidence, agency alerts, and follow-up.</small>
                    </div>
                    <div class="standard-card">
                        <strong>SMS fallback</strong>
                        <span>Continuity channel</span>
                        <small>Essential reporting when data service is weak or unavailable.</small>
                    </div>
                </div>

                <div class="product-stage" aria-label="BorderReach product preview">
                    <div class="desktop-preview">
                        <div class="preview-top">
                            <div class="window-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                            <strong>BorderReach Data</strong>
                            <span>Live operations view</span>
                        </div>
                        <div class="preview-body">
                            <div class="preview-side">
                                <strong>Workspace</strong>
                                <span>Summary</span>
                                <span>Forms</span>
                                <span>Data</span>
                                <span>Map</span>
                                <span>Team</span>
                            </div>
                            <div class="preview-main">
                                <div class="metrics">
                                    <div class="metric"><span>Total reports</span><strong>12,842</strong></div>
                                    <div class="metric"><span>Pending sync</span><strong>23</strong></div>
                                    <div class="metric"><span>Active posts</span><strong>48</strong></div>
                                    <div class="metric"><span>Review queue</span><strong>17</strong></div>
                                </div>
                                <div class="preview-grid">
                                    <div class="mini-panel">
                                        <strong>Reports over time</strong>
                                        <div class="bars" aria-hidden="true">
                                            <span style="height:52%;"></span>
                                            <span style="height:70%;"></span>
                                            <span style="height:42%;"></span>
                                            <span style="height:82%;"></span>
                                            <span style="height:64%;"></span>
                                            <span style="height:92%;"></span>
                                            <span style="height:76%;"></span>
                                        </div>
                                    </div>
                                    <div class="mini-panel">
                                        <strong>Submission map</strong>
                                        <div class="map-panel" aria-hidden="true"></div>
                                    </div>
                                    <div class="mini-panel records">
                                        <strong>Recent records</strong>
                                        <div class="record-line"><span>Doc 9303 inspection</span><span class="pill">accepted</span><span>2m</span></div>
                                        <div class="record-line"><span>Customs declaration</span><span class="pill">queued</span><span>offline</span></div>
                                        <div class="record-line"><span>Security incident</span><span class="pill">review</span><span>14m</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="phone-preview" aria-hidden="true">
                        <div class="phone-bar">
                            <span>Inspection Report</span>
                            <span>4/12</span>
                        </div>
                        <div class="phone-body">
                            <div class="step-dots"><span></span><span></span><span></span><span class="active"></span><span></span></div>
                            <div class="phone-field">Scan MRZ</div>
                            <div class="phone-field">Document number</div>
                            <div class="phone-field">Nationality</div>
                            <div class="phone-button">Next</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </header>

    <section class="stats" aria-label="BorderReach platform scale">
        <div class="shell stats-grid">
            <div class="stat"><strong>4</strong><span>Reporting domains: immigration, customs, health, and security</span></div>
            <div class="stat"><strong>Offline</strong><span>Report first and sync when connectivity returns</span></div>
            <div class="stat"><strong>Tenant</strong><span>Country-scoped workspaces, users, forms, posts, and data</span></div>
        </div>
    </section>

    <main>
        <section id="features">
            <div class="shell">
                <div class="section-head center">
                    <h2>Purpose-built data technology for border operations</h2>
                    <p>Standardize reports, supervise remote posts, manage national border operations, and protect operational records in one platform.</p>
                </div>
                <div class="feature-grid">
                    <article class="feature">
                        <div class="feature-icon">01</div>
                        <h3>Powerful form development</h3>
                        <p>Create versioned forms from standards-based templates and adapt them to national procedures.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">02</div>
                        <h3>Connectivity-independent reporting</h3>
                        <p>Keep posts reporting with step-based workflows, drafts, GPS custody, and sync retry.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">03</div>
                        <h3>National border management</h3>
                        <p>Manage country tenants, users, border posts, devices, setup QR codes, and roles.</p>
                    </article>
                    <article class="feature">
                        <div class="feature-icon">04</div>
                        <h3>Data protection and security</h3>
                        <p>Use authenticated access, audit trails, device context, role scoping, and secure exports.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="solution-section" id="solutions">
            <div class="shell solution-block">
                <div class="section-head">
                    <h2>Visibility beyond the main border gates</h2>
                    <p>Support daily reporting, inspections, incidents, referrals, and cross-agency coordination at the remote posts that are often hardest to supervise.</p>
                </div>
                <div class="capability-list">
                    <div class="capability"><strong>Remote post monitoring</strong><span>Track movement patterns, post activity, review queues, and sync health without waiting for paper reports from forgotten crossings.</span></div>
                    <div class="capability"><strong>Targeted assessments and inspections</strong><span>Use structured fields for travel documents, cargo, health screening, incidents, and officer decisions.</span></div>
                    <div class="capability"><strong>Flexible reporting</strong><span>Search, filter, map, export, and analyze submission data as soon as records sync.</span></div>
                </div>
            </div>
        </section>

        <section id="standards">
            <div class="shell solution-block">
                <div class="section-head">
                    <h2>Standards-backed report types</h2>
                    <p>Start with proven international baselines, then tailor each form to the operating context.</p>
                </div>
                <div class="capability-list">
                    <div class="capability"><strong>ICAO Doc 9303 immigration template</strong><span>Starter fields for MRZ, document identity, security observations, VIZ/MRZ checks, and decisions.</span></div>
                    <div class="capability"><strong>WCO Data Model customs report type</strong><span>Declaration, procedure, transport, HS code, inspection result, duty, seizure, and referral fields.</span></div>
                    <div class="capability"><strong>WHO IHR point-of-entry health report type</strong><span>Screening, symptoms, exposure risk, referral, public health action, and notification fields.</span></div>
                    <div class="capability"><strong>Security incident template</strong><span>Incident type, severity, detection source, agency notification, evidence, and follow-up ownership.</span></div>
                </div>
            </div>
        </section>

        <section class="solution-section" id="workflow">
            <div class="shell">
                <div class="section-head center">
                    <h2>From form design to analysis-ready data</h2>
                    <p>A simple workflow for national border management in places where infrastructure cannot be assumed.</p>
                </div>
                <div class="workflow">
                    <div class="workflow-step"><span>1</span><h3>Build</h3><p>Create forms and publish controlled versions.</p></div>
                    <div class="workflow-step"><span>2</span><h3>Deploy</h3><p>Assign posts, officers, devices, and setup QR codes.</p></div>
                    <div class="workflow-step"><span>3</span><h3>Report</h3><p>Complete structured reports offline in steps.</p></div>
                    <div class="workflow-step"><span>4</span><h3>Sync</h3><p>Submit when connectivity returns, with retry feedback.</p></div>
                    <div class="workflow-step"><span>5</span><h3>Analyze</h3><p>Review, map, filter, export, and monitor operations.</p></div>
                </div>
            </div>
        </section>

        <section id="deployment">
            @php
                $deploymentPlans = \App\Models\Country::deploymentPlanLabels();
                $deploymentTypes = \App\Models\Country::deploymentTypeLabels();
            @endphp
            <div class="shell">
                <div class="cta-grid">
                    <div class="cta-card">
                        <h2>Get started with a focused deployment.</h2>
                        <p>Start with one corridor, one agency workflow, or one standards-based form, then expand when the operational model is proven.</p>
                        <a class="btn primary" href="{{ route('login') }}">Enter Platform</a>
                    </div>
                    <div class="cta-card">
                        <h2>Request a deployment review.</h2>
                        <p>Tell us the country, agency, scale, and hosting environment so a rollout path can be prepared.</p>
                        <form class="request-form" method="POST" action="{{ route('deployment-requests.store') }}">
                            @csrf
                            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;height:1px;width:1px;" aria-hidden="true">
                            <div>
                                <label for="country_name">Country</label>
                                <input id="country_name" name="country_name" type="text" value="{{ old('country_name') }}" required>
                            </div>
                            <div>
                                <label for="agency_name">Agency</label>
                                <input id="agency_name" name="agency_name" type="text" value="{{ old('agency_name') }}" required>
                            </div>
                            <div>
                                <label for="contact_name">Contact Name</label>
                                <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name') }}" required>
                            </div>
                            <div>
                                <label for="contact_email">Work Email</label>
                                <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}" required>
                            </div>
                            <div>
                                <label for="deployment_plan">Plan</label>
                                <select id="deployment_plan" name="deployment_plan" required>
                                    @foreach($deploymentPlans as $plan => $label)
                                        <option value="{{ $plan }}" @selected(old('deployment_plan', \App\Models\Country::PLAN_PROGRAM) === $plan)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="deployment_type">Environment</label>
                                <select id="deployment_type" name="deployment_type" required>
                                    @foreach($deploymentTypes as $type => $label)
                                        <option value="{{ $type }}" @selected(old('deployment_type', \App\Models\Country::DEPLOYMENT_HOSTED) === $type)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="full">
                                <label for="message">Deployment Notes</label>
                                <textarea id="message" name="message">{{ old('message') }}</textarea>
                            </div>
                            <div class="full">
                                <button class="btn primary" type="submit">Send Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="shell footer-inner">
            <span>BorderReach is border reporting software for offline field operations.</span>
            <span>Immigration, customs, health, and security reporting in one workspace.</span>
        </div>
    </footer>
</div>
</body>
</html>
