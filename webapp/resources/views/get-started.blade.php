<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Choose a BorderReach managed workspace or private government deployment path.">
    <title>Get Started | BorderReach</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root {
            --ink: #273448;
            --title: #1d2637;
            --muted: #637083;
            --line: #dce6ef;
            --wash: #f6f8fb;
            --green: #08794f;
            --green-soft: #e8f6ef;
            --blue: #2588c7;
            --paper: #ffffff;
            --shadow: 0 18px 45px rgba(31, 48, 74, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
        }
        a { color: inherit; text-decoration: none; }
        .shell {
            width: min(1160px, calc(100% - 44px));
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
        .brand img { width: 176px; height: auto; }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
            color: #253247;
            font-size: 0.95rem;
            font-weight: 760;
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
            border-color: var(--blue);
            background: var(--blue);
            color: #ffffff;
            box-shadow: 0 12px 28px rgba(37, 136, 199, 0.18);
        }
        .flash {
            width: min(1160px, calc(100% - 44px));
            margin: 18px auto 0;
            border-radius: 8px;
            padding: 13px 16px;
            font-weight: 760;
        }
        .flash.success { border: 1px solid #b9e5c4; background: #effaf2; color: #135c37; }
        .flash.error { border: 1px solid #fecaca; background: #fff1f2; color: #9f1239; }
        .hero {
            padding: 102px 0 62px;
            text-align: center;
        }
        .hero h1 {
            max-width: 920px;
            margin: 0 auto;
            color: var(--title);
            font-size: 4.2rem;
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: 0;
        }
        .hero p {
            max-width: 680px;
            margin: 22px auto 0;
            color: var(--muted);
            font-size: 1.15rem;
        }
        .choice-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
            width: min(850px, 100%);
            margin: 0 auto 82px;
        }
        .choice-card {
            display: flex;
            flex-direction: column;
            min-height: 520px;
            border: 1px solid #eef2f6;
            border-radius: 8px;
            background: #fafbfe;
            padding: 34px;
            box-shadow: 0 1px 0 rgba(31, 48, 74, 0.02);
        }
        .choice-icon {
            display: grid;
            place-items: center;
            width: 54px;
            height: 54px;
            border-radius: 8px;
            background: var(--blue);
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 900;
        }
        .choice-icon.private { background: var(--green); }
        .choice-card h2 {
            margin: 36px 0 0;
            color: var(--title);
            font-size: 2rem;
            line-height: 1.18;
            font-weight: 900;
        }
        .choice-card p {
            margin: 24px 0 0;
            color: #4f5f73;
            font-size: 1.02rem;
        }
        .choice-actions {
            display: grid;
            gap: 16px;
            margin-top: auto;
            padding-top: 34px;
        }
        .signin-link {
            display: inline-flex;
            justify-content: center;
            color: #0965a8;
            font-size: 1.05rem;
            font-weight: 900;
        }
        .deployment-section {
            border-top: 1px solid var(--line);
            background: var(--wash);
            padding: 78px 0;
        }
        .section-head {
            max-width: 760px;
            margin: 0 auto 30px;
            text-align: center;
        }
        .section-head h2 {
            margin: 0;
            color: var(--title);
            font-size: 2.6rem;
            line-height: 1.05;
            font-weight: 900;
        }
        .section-head p {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 1rem;
        }
        .request-panel {
            width: min(900px, 100%);
            margin: 0 auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .request-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
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
        .request-form textarea { min-height: 104px; resize: vertical; }
        .request-form .full { grid-column: 1 / -1; }
        .module-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .module-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            margin: 0;
            border: 1px solid #dce6ef;
            border-radius: 8px;
            padding: 0 10px;
            background: #fbfdff;
            font-size: 0.82rem;
        }
        footer {
            border-top: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            padding: 34px 0;
            font-size: 0.94rem;
        }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .nav-links { display: none; }
            .hero h1 { font-size: 3rem; }
            .choice-grid,
            .request-form { grid-template-columns: 1fr; }
            .choice-card { min-height: auto; }
            .module-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .shell { width: min(100% - 28px, 1160px); }
            .nav { min-height: 74px; }
            .brand img { width: 148px; }
            .hero { padding: 64px 0 42px; }
            .hero h1 { font-size: 2.2rem; }
            .hero p { font-size: 0.98rem; }
            .choice-card { padding: 24px; }
            .choice-card h2 { font-size: 1.6rem; }
            .section-head h2 { font-size: 2rem; }
            .module-list { grid-template-columns: 1fr; }
            .footer-inner {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="shell nav">
        <a class="brand" href="{{ route('welcome') }}" aria-label="BorderReach home">
            <img src="{{ asset('images/borderreach-logo.svg') }}" alt="BorderReach">
        </a>
        <nav class="nav-links" aria-label="Primary navigation">
            <a href="{{ url('/#features') }}">Features</a>
            <a href="{{ url('/#solutions') }}">Services</a>
            <a href="{{ url('/#deployment') }}">Enterprise</a>
            <a href="{{ url('/#standards') }}">Resources</a>
            <a href="{{ url('/#deployment') }}">Contact</a>
        </nav>
        <a class="btn" href="{{ route('login') }}">Login</a>
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
            <h1>Sign in or request a BorderReach workspace</h1>
            <p>Choose the access path that fits your agency environment. Existing users can sign in; new government deployments start with a short review.</p>
        </div>
    </section>
</header>

<main>
    <section class="shell" aria-label="Choose a BorderReach deployment path">
        <div class="choice-grid">
            <article class="choice-card">
                <div class="choice-icon" aria-hidden="true">◎</div>
                <h2>Managed BorderReach workspace</h2>
                <p>For agencies that want a ready BorderReach environment with country-scoped projects, users, forms, mobile setup, dashboards, and exports managed from one secure workspace.</p>
                <div class="choice-actions">
                    <a class="btn primary" href="#deployment-request">Request a workspace</a>
                    <a class="signin-link" href="{{ route('login') }}">Sign in →</a>
                </div>
            </article>
            <article class="choice-card">
                <div class="choice-icon private" aria-hidden="true">▣</div>
                <h2>Private government deployment</h2>
                <p>For ministries or national border agencies that need private cloud, on-premise, or hybrid deployment with their own domain, data controls, and rollout plan.</p>
                <div class="choice-actions">
                    <a class="btn primary" href="#deployment-request">Request deployment review</a>
                    <a class="signin-link" href="{{ route('login') }}">Sign in →</a>
                </div>
            </article>
        </div>
    </section>

    <section id="deployment-request" class="deployment-section">
        @php
            $deploymentPlans = \App\Models\Country::deploymentPlanLabels();
            $deploymentTypes = \App\Models\Country::deploymentTypeLabels();
            $moduleOptions = ['immigration' => 'Immigration', 'customs' => 'Customs', 'health' => 'Health', 'security' => 'Security'];
            $selectedModules = old('modules', ['immigration']);
        @endphp
        <div class="shell">
            <div class="section-head">
                <h2>Tell us about the deployment</h2>
                <p>Share the country, agency, expected scope, and preferred environment. The BorderReach team can prepare the right setup path from there.</p>
            </div>
            <div class="request-panel">
                <form class="request-form" method="POST" action="{{ route('deployment-requests.store') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="get-started">
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
                        <label for="contact_phone">Phone</label>
                        <input id="contact_phone" name="contact_phone" type="text" value="{{ old('contact_phone') }}">
                    </div>
                    <div>
                        <label for="contact_role">Role</label>
                        <input id="contact_role" name="contact_role" type="text" value="{{ old('contact_role') }}">
                    </div>
                    <div>
                        <label for="deployment_plan">Deployment Scale</label>
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
                    <div>
                        <label for="expected_posts">Expected Border Posts</label>
                        <input id="expected_posts" name="expected_posts" type="number" min="1" value="{{ old('expected_posts') }}">
                    </div>
                    <div>
                        <label for="expected_users">Expected Users</label>
                        <input id="expected_users" name="expected_users" type="number" min="1" value="{{ old('expected_users') }}">
                    </div>
                    <div class="full">
                        <label>Reporting Modules</label>
                        <div class="module-list">
                            @foreach($moduleOptions as $module => $label)
                                <label>
                                    <input type="checkbox" name="modules[]" value="{{ $module }}" @checked(in_array($module, $selectedModules, true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
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
    </section>
</main>

<footer>
    <div class="shell footer-inner">
        <span>BorderReach is border reporting software for offline field operations.</span>
        <span>Existing deployment? <a href="{{ route('login') }}">Sign in</a></span>
    </div>
</footer>
</body>
</html>
