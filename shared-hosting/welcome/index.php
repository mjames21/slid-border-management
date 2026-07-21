<?php
$to = 'border.reach.onboarding@memeh.org';
$sent = false;
$errors = [];

$old = [
    'country' => '',
    'agency' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => '',
    'environment' => 'Managed hosted workspace',
    'message' => '',
];

$moduleOptions = ['Immigration', 'Customs', 'Health', 'Security'];
$selectedModules = ['Immigration'];
$countryOptions = [
    'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina',
    'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados',
    'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana',
    'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia', 'Cameroon',
    'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo',
    'Costa Rica', "Cote d'Ivoire", 'Croatia', 'Cuba', 'Cyprus', 'Czechia',
    'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic',
    'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini',
    'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana',
    'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea (Conakry)', 'Guinea-Bissau', 'Guyana',
    'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland',
    'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kosovo',
    'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya',
    'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives',
    'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia',
    'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia',
    'Nauru', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea',
    'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine', 'Panama',
    'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania',
    'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines',
    'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia',
    'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia',
    'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname',
    'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste',
    'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda',
    'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan',
    'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $value) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $selectedModules = array_values(array_intersect(
        $moduleOptions,
        array_map('strval', $_POST['modules'] ?? [])
    ));

    $honeypot = trim((string) ($_POST['website'] ?? ''));

    if ($honeypot !== '') {
        $sent = true;
    } else {
        if ($old['country'] === '') {
            $errors[] = 'Country is required.';
        }

        if ($old['agency'] === '') {
            $errors[] = 'Agency is required.';
        }

        if ($old['name'] === '') {
            $errors[] = 'Contact name is required.';
        }

        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid work email is required.';
        }

        if ($selectedModules === []) {
            $selectedModules = ['Immigration'];
        }

        if ($errors === []) {
            $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            $subject = 'BorderReach deployment request';
            $body = implode("\n", [
                'Country: ' . $old['country'],
                'Agency: ' . $old['agency'],
                'Contact: ' . $old['name'],
                'Email: ' . $old['email'],
                'Phone: ' . ($old['phone'] ?: '-'),
                'Role: ' . ($old['role'] ?: '-'),
                'Environment: ' . $old['environment'],
                'Modules: ' . implode(', ', $selectedModules),
                '',
                'Message:',
                $old['message'] ?: '-',
            ]);

            $headers = [
                'From: BorderReach Website <no-reply@' . $host . '>',
                'Reply-To: ' . $old['name'] . ' <' . $old['email'] . '>',
                'Content-Type: text/plain; charset=UTF-8',
            ];

            $sent = mail($to, $subject, $body, implode("\r\n", $headers));

            if (!$sent) {
                $errors[] = 'The message could not be sent. Please email border.reach.onboarding@memeh.org directly.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BorderReach is standardized border reporting software for offline field operations, synced review, maps, exports, and analysis.">
    <title>BorderReach | Standardized Border Reporting</title>
    <link rel="icon" href="assets/borderreach-mark.svg" type="image/svg+xml">
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
        .brand {
            display: inline-flex;
            align-items: center;
        }
        .brand-logo {
            width: 210px;
            height: auto;
        }
        .nav-links,
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 22px;
            font-size: 0.94rem;
            font-weight: 800;
            color: #263247;
        }
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
                url("assets/borderreach-remote-border-road.png");
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
            font-size: clamp(3.1rem, 7vw, 6rem);
            line-height: 0.95;
            letter-spacing: 0;
            font-weight: 950;
        }
        .hero-lede {
            margin: 22px 0 0;
            max-width: 650px;
            font-size: clamp(1.25rem, 2vw, 1.7rem);
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
            padding: 36px 0;
        }
        .use-case-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
        }
        .use-case-copy { max-width: 760px; }
        .use-case-copy .eyebrow {
            margin-bottom: 8px;
            color: var(--green-dark);
        }
        .use-case-copy h2 {
            margin: 0;
            color: var(--title);
            font-size: clamp(1.5rem, 3vw, 2rem);
            line-height: 1.08;
        }
        .use-case-copy p {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 1.02rem;
            font-weight: 650;
        }
        .agency-card {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 300px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--wash);
        }
        .agency-card img {
            width: 68px;
            height: 68px;
            object-fit: contain;
        }
        .agency-card strong {
            display: block;
            color: var(--title);
            line-height: 1.2;
        }
        .agency-card span {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: .92rem;
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
            font-size: clamp(2rem, 4vw, 3.1rem);
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
            min-height: 230px;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
        }
        .standard .tag {
            display: inline-flex;
            margin-bottom: 18px;
            padding: 7px 10px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-dark);
            font-size: 0.75rem;
            font-weight: 900;
            text-transform: uppercase;
        }
        .standard h3 {
            margin: 0;
            color: var(--title);
            font-size: 1.16rem;
            line-height: 1.18;
            font-weight: 950;
        }
        .standard p {
            margin: 12px 0 0;
            color: var(--muted);
            font-weight: 560;
        }
        .contact {
            background: #ffffff;
        }
        .contact-panel {
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(360px, 1.1fr);
            gap: 34px;
            padding: 34px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: var(--shadow);
        }
        .contact-info {
            padding: 16px 0;
        }
        .contact-info p {
            color: var(--muted);
            font-size: 1.04rem;
            font-weight: 560;
        }
        .email-link {
            color: var(--green-dark);
            font-weight: 900;
            overflow-wrap: anywhere;
        }
        form {
            display: grid;
            gap: 14px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        label {
            display: grid;
            gap: 7px;
            color: #2f3b4c;
            font-size: 0.84rem;
            font-weight: 900;
        }
        input,
        select,
        textarea {
            width: 100%;
            min-height: 46px;
            border: 1px solid #cbd7e6;
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--title);
            background: #ffffff;
            font: inherit;
        }
        textarea {
            min-height: 118px;
            resize: vertical;
        }
        .module-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .module {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 9px 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-weight: 820;
        }
        .module input {
            width: auto;
            min-height: auto;
        }
        .hidden { display: none; }
        footer {
            padding: 30px 0;
            color: #718096;
            border-top: 1px solid var(--line);
            font-size: 0.9rem;
            font-weight: 650;
        }

        @media (max-width: 920px) {
            .nav-links { display: none; }
            .feature-grid,
            .standard-grid,
            .two-col,
            .contact-panel {
                grid-template-columns: 1fr;
            }
            .feature {
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }
            .feature:last-child { border-bottom: 0; }
            .use-case-inner {
                align-items: flex-start;
                flex-direction: column;
            }
            .agency-card {
                width: 100%;
                min-width: 0;
            }
        }
        @media (max-width: 640px) {
            .shell,
            .flash { width: min(100% - 24px, 1160px); }
            .nav-inner { min-height: 68px; }
            .brand-logo { width: 178px; }
            .hero,
            .hero .shell { min-height: 600px; }
            .hero-copy { padding: 58px 0; }
            h1 { font-size: clamp(2.55rem, 15vw, 4rem); }
            section { padding: 54px 0; }
            .form-grid,
            .module-grid { grid-template-columns: 1fr; }
            .contact-panel { padding: 20px; }
        }
    </style>
</head>
<body>
    <nav class="site-nav">
        <div class="shell nav-inner">
            <a class="brand" href="#" aria-label="BorderReach home">
                <img class="brand-logo" src="assets/borderreach-logo.svg" alt="BorderReach">
            </a>
            <div class="nav-links">
                <a href="#platform">Platform</a>
                <a href="#standards">Standards</a>
                <a href="#deployment">Deployment</a>
            </div>
            <div class="nav-actions">
                <a class="btn primary" href="#deployment">Request review</a>
            </div>
        </div>
    </nav>

    <?php if ($sent && $errors === []): ?>
        <div class="flash success">Thank you. Your BorderReach onboarding request has been sent.</div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="flash error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <header class="hero">
        <div class="shell">
            <div class="hero-copy">
                <p class="eyebrow">Offline first border reporting</p>
                <h1>Standardized border reporting that keeps working offline.</h1>
                <p class="hero-lede">Report trusted movement, inspection, health, customs, and incident records from difficult operating environments.</p>
                <p class="hero-text">BorderReach helps national border management agencies use standards-based forms, controlled mobile setup, GPS custody, review queues, maps, exports, and analysis when connectivity returns.</p>
                <div class="hero-actions">
                    <a class="btn primary" href="#deployment">Request deployment review</a>
                    <a class="btn ghost" href="#standards">View standards</a>
                </div>
            </div>
        </div>
    </header>

    <div class="feature-strip" id="platform">
        <div class="shell feature-grid">
            <div class="feature"><strong>Offline reporting</strong><span>Capture first. Sync later.</span></div>
            <div class="feature"><strong>Standardized forms</strong><span>ICAO, WCO, WHO, and incidents.</span></div>
            <div class="feature"><strong>Location aware</strong><span>Post assignment, GPS, and map context.</span></div>
            <div class="feature"><strong>Review and export</strong><span>Search, inspect, download, and integrate.</span></div>
        </div>
    </div>

    <section class="use-case" aria-label="Sierra Leone Immigration Service use case">
        <div class="shell use-case-inner">
            <div class="use-case-copy">
                <p class="eyebrow">Operational use case</p>
                <h2>Used by Sierra Leone Immigration Service for border management.</h2>
                <p>Sierra Leone Immigration Service is using BorderReach to support standardized border reporting, officer assignment, GPS context, and synced operational review across border posts.</p>
            </div>
            <div class="agency-card">
                <img src="assets/sierra-leone-immigration-logo.png" alt="Sierra Leone Immigration Service logo">
                <div>
                    <strong>Sierra Leone Immigration Service</strong>
                    <span>Border management deployment</span>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="shell two-col">
            <div>
                <div class="section-head">
                    <h2>Built for border posts where connectivity cannot be assumed.</h2>
                    <p>Remote border operations still need complete, reviewable records. BorderReach keeps the reporting workflow structured from field capture through analysis.</p>
                </div>
                <div class="check-list">
                    <div class="check">Assign officers to posts, modules, and authorized projects.</div>
                    <div class="check">Publish controlled form versions and keep older records traceable.</div>
                    <div class="check">Send clean records to maps, dashboards, exports, and external systems.</div>
                </div>
            </div>
            <div class="photo-frame">
                <img src="assets/borderreach-customs-post.png" alt="Remote border post and customs checkpoint">
            </div>
        </div>
    </section>

    <section class="standards" id="standards">
        <div class="shell">
            <div class="section-head center">
                <h2>Templates grounded in border operations standards.</h2>
                <p>Start with a protected baseline, clone it into a project, then adapt the questions for the country, agency, and border workflow.</p>
            </div>
            <div class="standard-grid">
                <article class="standard">
                    <span class="tag">Immigration</span>
                    <h3>ICAO Doc 9303 inspection</h3>
                    <p>Travel document and MRZ-oriented fields for identity, document validity, VIZ/MRZ checks, movement, visa, and officer decision records.</p>
                </article>
                <article class="standard">
                    <span class="tag">Customs</span>
                    <h3>WCO Data Model</h3>
                    <p>Declaration, procedure, goods, transport, consignor, consignee, duty, inspection outcome, and seizure reporting structure.</p>
                </article>
                <article class="standard">
                    <span class="tag">Health</span>
                    <h3>WHO IHR point of entry</h3>
                    <p>Screening, symptoms, exposure risk, vaccination or prophylaxis document checks, public health action, referral, and notification fields.</p>
                </article>
                <article class="standard">
                    <span class="tag">Security</span>
                    <h3>Incident reporting</h3>
                    <p>Structured incident type, severity, detection source, action taken, agency notification, evidence, and follow-up ownership.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="contact" id="deployment">
        <div class="shell contact-panel">
            <div class="contact-info">
                <p class="eyebrow" style="color: var(--green-dark)">Deployment review</p>
                <h2>Tell us about the deployment.</h2>
                <p>Share the country, agency, expected scope, and preferred hosting environment. The BorderReach onboarding team can prepare the right setup path from there.</p>
                <p>Direct email: <a class="email-link" href="mailto:border.reach.onboarding@memeh.org">border.reach.onboarding@memeh.org</a></p>
            </div>
            <form method="post" action="#deployment">
                <input class="hidden" type="text" name="website" value="" tabindex="-1" autocomplete="off">
                <div class="form-grid">
                <label>Country
                    <input name="country" value="<?= e($old['country']) ?>" list="country-options" autocomplete="country-name" placeholder="Start typing a country" required>
                </label>
                    <label>Agency
                        <input name="agency" value="<?= e($old['agency']) ?>" required>
                    </label>
                    <label>Contact name
                        <input name="name" value="<?= e($old['name']) ?>" required>
                    </label>
                    <label>Work email
                        <input type="email" name="email" value="<?= e($old['email']) ?>" required>
                    </label>
                    <label>Phone
                        <input name="phone" value="<?= e($old['phone']) ?>">
                    </label>
                    <label>Role
                        <input name="role" value="<?= e($old['role']) ?>">
                    </label>
                    <label>Environment
                        <select name="environment">
                            <?php foreach (['Managed hosted workspace', 'Private cloud', 'On premise', 'Hybrid'] as $environment): ?>
                                <option value="<?= e($environment) ?>" <?= $old['environment'] === $environment ? 'selected' : '' ?>><?= e($environment) ?></option>
                            <?php endforeach; ?>
                        </select>
                </label>
            </div>
            <datalist id="country-options">
                <?php foreach ($countryOptions as $country): ?>
                    <option value="<?= e($country) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <label>Reporting modules</label>
                <div class="module-grid">
                    <?php foreach ($moduleOptions as $module): ?>
                        <label class="module">
                            <input type="checkbox" name="modules[]" value="<?= e($module) ?>" <?= in_array($module, $selectedModules, true) ? 'checked' : '' ?>>
                            <?= e($module) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label>Deployment notes
                    <textarea name="message"><?= e($old['message']) ?></textarea>
                </label>
                <button class="btn primary" type="submit">Send request</button>
            </form>
        </div>
    </section>

    <footer>
        <div class="shell">BorderReach. Standardized border reporting for difficult operating environments.</div>
    </footer>
</body>
</html>
