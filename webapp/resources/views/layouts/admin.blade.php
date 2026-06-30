@php
    $showAdminShell = auth()->check() && auth()->user()?->is_admin;

    $navGroups = [
        'Workspace' => [
            ['label' => 'Projects', 'route' => 'admin.projects.index', 'active' => ['admin.projects.*', 'admin.forms.*']],
            ['label' => 'Data', 'route' => 'admin.submissions.index', 'active' => 'admin.submissions.*'],
            ['label' => 'Map', 'route' => 'admin.map.index', 'active' => 'admin.map.*'],
            ['label' => 'Analysis', 'route' => 'admin.dashboard.index', 'active' => 'admin.dashboard.*'],
            ['label' => 'REST Services', 'route' => 'admin.webhooks.index', 'active' => ['admin.webhooks.*', 'admin.webhook-deliveries.*']],
        ],
        'Configuration' => [
            ['label' => 'Countries', 'route' => 'admin.countries.index', 'active' => 'admin.countries.*'],
            ['label' => 'Border Posts', 'route' => 'admin.border-posts.index', 'active' => 'admin.border-posts.*'],
            ['label' => 'Locations', 'route' => 'admin.locations.index', 'active' => 'admin.locations.*'],
        ],
        'Administration' => [
            ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
            ['label' => 'Deployment Requests', 'route' => 'admin.deployment-requests.index', 'active' => 'admin.deployment-requests.*'],
            ['label' => 'Profile', 'route' => 'profile.show', 'active' => 'profile.*'],
        ],
    ];

    $shellProjects = collect();
    $shellCounts = ['deployed' => 0, 'draft' => 0, 'archived' => 0];
    $currentRouteForm = request()->route('form');
    $currentRouteFormId = $currentRouteForm instanceof \App\Models\DynamicForm ? $currentRouteForm->id : null;
    $currentWorkspaceTitle = $currentRouteForm instanceof \App\Models\DynamicForm ? $currentRouteForm->title : 'BorderReach Workspace';
    $userName = auth()->user()?->name ?: 'User';
    $userInitials = collect(explode(' ', trim($userName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    if ($showAdminShell && \Illuminate\Support\Facades\Schema::hasTable('dynamic_forms')) {
        $shellCountryFilter = request('country_code');
        $shellProjectQuery = \App\Models\DynamicForm::query()
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('dynamic_forms', 'is_template'), fn ($query) => $query->where('is_template', false))
            ->when($shellCountryFilter, fn ($query, $countryCode) => $query->where('country_code', $countryCode));

        $shellCounts['deployed'] = (clone $shellProjectQuery)->whereNotNull('published_version_id')->count();
        $shellCounts['draft'] = (clone $shellProjectQuery)->whereNull('published_version_id')->count();
        $shellProjects = (clone $shellProjectQuery)
            ->with('country')
            ->latest('updated_at')
            ->limit(18)
            ->get();
    }

    $projectTabs = [
        ['label' => 'Summary', 'route' => 'admin.projects.index', 'active' => ['admin.projects.*']],
        ['label' => 'Form', 'route' => 'admin.forms.index', 'active' => ['admin.forms.*']],
        ['label' => 'Data', 'route' => 'admin.submissions.index', 'active' => ['admin.submissions.*']],
        ['label' => 'Map', 'route' => 'admin.map.index', 'active' => ['admin.map.*']],
        ['label' => 'Settings', 'route' => 'admin.webhooks.index', 'active' => ['admin.webhooks.*', 'admin.webhook-deliveries.*', 'admin.countries.*', 'admin.border-posts.*', 'admin.locations.*', 'admin.users.*', 'admin.deployment-requests.*', 'profile.*']],
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BorderReach Console</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite('resources/js/app.js')
    <style>
        :root {
            --ink: #26374a;
            --title: #1e2a3a;
            --muted: #637083;
            --line: #dce5ef;
            --paper: #ffffff;
            --wash: #f5f8fb;
            --soft: #eef6f2;
            --green: #4da34f;
            --green-dark: #177245;
            --blue: #2588c7;
            --navy: #123f59;
            --danger: #b42318;
            --shadow: 0 18px 45px rgba(31, 48, 74, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: var(--wash);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }
        a { color: inherit; }
        .admin-shell { display: grid; grid-template-columns: 278px minmax(0, 1fr); min-height: 100vh; }
        .sidebar {
            position: sticky;
            top: 0;
            align-self: start;
            display: flex;
            flex-direction: column;
            height: 100vh;
            border-right: 1px solid var(--line);
            background: #ffffff;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 82px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
            color: var(--title);
            text-decoration: none;
        }
        .sidebar-brand img { width: 48px; height: 48px; object-fit: contain; }
        .brand-title { display: block; font-weight: 900; line-height: 1.02; }
        .brand-subtitle { display: block; margin-top: 4px; color: var(--muted); font-size: 12px; font-weight: 720; }
        .sidebar-nav { flex: 1; overflow: auto; padding: 18px 14px; }
        .nav-group { margin-bottom: 20px; }
        .nav-heading {
            margin: 0 0 8px;
            padding: 0 10px;
            color: #7b8797;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border-radius: 8px;
            padding: 10px 11px;
            color: #425166;
            text-decoration: none;
            font-size: 14px;
            font-weight: 760;
        }
        .nav-link:hover { background: #f1f6fb; color: var(--title); }
        .nav-link.active { background: #eaf5ef; color: #17633e; }
        .nav-link.active::after { content: ""; width: 7px; height: 7px; border-radius: 999px; background: var(--green); }
        .sidebar-footer { border-top: 1px solid var(--line); padding: 16px; }
        .user-meta { margin-bottom: 12px; font-size: 13px; }
        .user-meta strong { display: block; color: var(--title); }
        .user-meta span { display: block; overflow: hidden; color: var(--muted); text-overflow: ellipsis; white-space: nowrap; }
        .admin-main { min-width: 0; }
        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--line);
            background: #ffffff;
            padding: 14px 26px;
        }
        .admin-topbar strong { color: var(--title); }
        .topbar-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .topbar-search {
            width: min(360px, 42vw);
            min-height: 38px;
            border: 1px solid #cdd8e4;
            border-radius: 8px;
            background: #f8fbfe;
            padding: 0 12px;
            color: var(--ink);
            font: inherit;
            font-size: 13px;
        }
        .container { max-width: 1240px; margin: 0 auto; padding: 28px; }
        .guest-shell .container { max-width: 760px; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }
        .title { margin: 0; color: var(--title); font-size: 30px; line-height: 1.08; font-weight: 900; letter-spacing: 0; }
        .subtitle { margin: 8px 0 0; color: var(--muted); }
        .card {
            margin-bottom: 20px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 20px;
            box-shadow: 0 1px 0 rgba(31, 48, 74, 0.02);
        }
        .panel {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
            background: #ffffff;
        }
        .panel-title { margin: 0; color: var(--title); font-size: 18px; font-weight: 900; }
        .panel-subtitle { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .status { margin-bottom: 20px; border: 1px solid #b9e5c4; border-radius: 8px; background: #effaf2; color: #135c37; padding: 12px 14px; }
        .error { margin-bottom: 20px; border: 1px solid #fecaca; border-radius: 8px; background: #fff1f2; color: #9f1239; padding: 12px 14px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .button, button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            border: 1px solid var(--green-dark);
            border-radius: 8px;
            padding: 0 14px;
            background: var(--green-dark);
            color: #ffffff;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 820;
        }
        .button.secondary, button.secondary { border-color: #40576b; background: #40576b; }
        .button.light, button.light { border-color: #cdd8e4; background: #ffffff; color: var(--title); }
        .button.blue, button.blue { border-color: var(--blue); background: var(--blue); color: #ffffff; }
        label { display: block; margin-bottom: 6px; color: #314156; font-size: 13px; font-weight: 840; }
        input[type="text"], input[type="password"], input[type="file"], input[type="search"], input[type="number"], input[type="email"], select, textarea {
            width: 100%;
            border: 1px solid #cbd7e3;
            border-radius: 8px;
            box-sizing: border-box;
            background: #ffffff;
            color: var(--ink);
            padding: 10px 12px;
            font: inherit;
        }
        textarea { min-height: 82px; resize: vertical; }
        input:focus, select:focus, textarea:focus { outline: 3px solid rgba(37, 136, 199, 0.16); border-color: var(--blue); }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .filter-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 14px; align-items: end; }
        .metric-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .metric-card { border: 1px solid var(--line); border-radius: 8px; background: #ffffff; padding: 16px; }
        .metric-card span { display: block; color: var(--muted); font-size: 12px; font-weight: 850; }
        .metric-card strong { display: block; margin-top: 7px; color: var(--title); font-size: 28px; line-height: 1; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 13px 14px; border-bottom: 1px solid #e6edf4; vertical-align: top; }
        th { background: #f7fafc; color: #536175; font-size: 12px; font-weight: 900; letter-spacing: .03em; text-transform: uppercase; }
        tbody tr:hover { background: #fbfdff; }
        .mono {
            font-family: Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            white-space: pre-wrap;
            word-break: break-word;
            border: 1px solid #e1e9f1;
            border-radius: 7px;
            background: #f8fbfe;
            padding: 8px 10px;
            font-size: 12px;
        }
        .mono.inline {
            display: inline-block;
            max-width: 190px;
            overflow: hidden;
            padding: 3px 7px;
            border-radius: 999px;
            text-overflow: ellipsis;
            vertical-align: middle;
            white-space: nowrap;
        }
        .tag { display: inline-flex; align-items: center; min-height: 24px; border-radius: 999px; padding: 0 9px; background: #e8eef4; color: #405166; font-size: 12px; font-weight: 840; }
        .tag.published, .tag.accepted { background: #e5f7ec; color: #19683e; }
        .tag.draft, .tag.queued { background: #fff5d7; color: #8a5a00; }
        .tag.rejected, .tag.failed { background: #ffe6e4; color: var(--danger); }
        .field-help { margin-top: 6px; color: var(--muted); font-size: 12px; }
        .record-title { color: var(--title); font-weight: 880; }
        .record-muted { color: var(--muted); font-size: 13px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary-card { border: 1px solid var(--line); border-radius: 8px; background: #ffffff; padding: 16px; }
        .summary-card span { display: block; color: var(--muted); font-size: 12px; font-weight: 850; text-transform: uppercase; letter-spacing: .04em; }
        .summary-card strong { display: block; margin-top: 7px; color: var(--title); font-size: 20px; line-height: 1.2; }
        .summary-card small { display: block; margin-top: 8px; color: var(--muted); line-height: 1.45; }
        .summary-item { border: 1px solid var(--line); border-radius: 8px; background: #ffffff; padding: 15px; }
        .summary-item label { color: var(--muted); text-transform: uppercase; letter-spacing: .04em; font-size: 11px; }
        .summary-item div:not(.mono) { color: var(--title); font-weight: 820; }
        .builder-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 18px; align-items: center; }
        .template-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .template-card { border: 1px solid var(--line); border-radius: 8px; background: #ffffff; padding: 18px; }
        .template-card strong { color: var(--title); }
        .template-grid-wide { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .template-card-detailed { display: flex; flex-direction: column; gap: 14px; min-height: 100%; }
        .template-card-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
        .template-card-head h3 { margin: 4px 0 0; color: var(--title); font-size: 18px; }
        .template-kicker { color: var(--muted); text-transform: uppercase; font-size: 11px; font-weight: 800; letter-spacing: .08em; }
        .template-card-detailed p { margin: 0; color: var(--muted); line-height: 1.55; }
        .template-standard { border: 1px solid var(--line); border-radius: 8px; background: #f8fafc; padding: 10px 12px; color: var(--title); font-size: 13px; font-weight: 700; }
        .template-section-list { display: grid; gap: 8px; }
        .template-section-list div { border-left: 3px solid var(--accent); padding-left: 10px; }
        .template-section-list span { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; line-height: 1.45; }
        .template-field-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .template-field-chips span { border: 1px solid var(--line); border-radius: 999px; padding: 5px 8px; color: var(--muted); background: #fff; font-size: 12px; }
        .template-actions { display: flex; gap: 10px; align-items: center; margin-top: auto; }
        .template-actions form { margin: 0; }
        .section-heading-row { margin: 20px 0 12px; display: flex; justify-content: space-between; align-items: end; gap: 16px; }
        .section-heading-row h2 { margin: 0; color: var(--title); font-size: 22px; }
        .section-heading-row p { margin: 4px 0 0; color: var(--muted); }
        .builder-table input, .builder-table select, .builder-table textarea { font-size: 13px; }
        .builder-table .required-cell, .builder-table .include-cell { text-align: center; vertical-align: middle; }
        .builder-table .required-cell input, .builder-table .include-cell input { width: auto; }
        .builder-table textarea { min-height: 70px; }
        .builder-question-list { display: grid; gap: 14px; padding: 18px; }
        .builder-question-card { border: 1px solid var(--line); border-radius: 8px; background: #fff; overflow: hidden; }
        .builder-section-card { background: #f8fbff; border-color: #bfdbfe; }
        .builder-question-main { display: grid; grid-template-columns: 56px 1fr; }
        .builder-question-include { display: flex; justify-content: center; align-items: flex-start; padding-top: 28px; background: #f8fafc; border-right: 1px solid var(--line); }
        .builder-question-include input { width: auto; }
        .builder-question-body { padding: 18px; }
        .builder-question-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
        .builder-question-head h3 { margin: 4px 0 0; color: var(--title); font-size: 17px; }
        .builder-question-tags { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: flex-end; }
        .required-toggle { display: inline-flex; gap: 6px; align-items: center; margin: 0; }
        .required-toggle input { width: auto; }
        .builder-question-purpose { margin: 10px 0 16px; color: var(--muted); line-height: 1.5; }
        .builder-card-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .builder-options-panel { margin-top: 16px; border-top: 1px solid var(--line); padding-top: 12px; }
        .builder-options-panel summary { cursor: pointer; color: var(--accent); font-weight: 800; margin-bottom: 12px; }
        .builder-options-panel textarea { min-height: 86px; }
        .builder-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .project-toolbar {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 18px;
        }
        .project-toolbar .filters {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr) auto;
            gap: 12px;
            align-items: end;
            flex: 1;
        }
        .project-toolbar .actions {
            justify-content: flex-end;
        }
        .project-list {
            display: grid;
            gap: 13px;
        }
        .project-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 1px 0 rgba(31, 48, 74, 0.02);
            overflow: hidden;
        }
        .project-card-main {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            padding: 18px;
        }
        .project-name {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .project-icon {
            display: inline-grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: #e8f4ef;
            color: var(--green-dark);
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .02em;
            flex: 0 0 auto;
        }
        .project-name a {
            color: var(--title);
            text-decoration: none;
            font-size: 18px;
            font-weight: 900;
        }
        .project-name a:hover { color: var(--green-dark); }
        .project-description {
            max-width: 780px;
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }
        .project-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .project-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(86px, 1fr));
            gap: 10px;
            min-width: 300px;
        }
        .project-stat {
            border: 1px solid #e2eaf2;
            border-radius: 8px;
            background: #fbfdff;
            padding: 10px;
        }
        .project-stat span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .project-stat strong {
            display: block;
            margin-top: 5px;
            color: var(--title);
            font-size: 18px;
            line-height: 1;
        }
        .project-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            border-top: 1px solid #e6edf4;
            background: #fbfdff;
            padding: 12px 18px;
        }
        .workspace-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            padding: 16px 18px;
        }
        .workspace-crumb {
            color: var(--muted);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .workspace-title {
            margin: 4px 0 0;
            color: var(--title);
            font-size: 24px;
            line-height: 1.1;
            font-weight: 900;
        }
        .workspace-meta {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }
        .workspace-tabs {
            display: flex;
            align-items: center;
            gap: 2px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--line);
            overflow-x: auto;
        }
        .workspace-tab {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0 18px;
            color: #526173;
            font-size: 14px;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
        }
        .workspace-tab.active { color: var(--green-dark); }
        .workspace-tab.active::after {
            content: "";
            position: absolute;
            right: 12px;
            bottom: -1px;
            left: 12px;
            height: 3px;
            border-radius: 999px 999px 0 0;
            background: var(--green-dark);
        }
        .data-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            background: #ffffff;
        }
        .data-toolbar .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .tool-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 34px;
            border: 1px solid #cdd8e4;
            border-radius: 7px;
            background: #ffffff;
            color: #314156;
            padding: 0 10px;
            font-size: 13px;
            font-weight: 820;
            text-decoration: none;
        }
        .tool-button.primary {
            border-color: var(--green-dark);
            background: var(--green-dark);
            color: #ffffff;
        }
        .selected-count {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            border-radius: 7px;
            background: #eef4f9;
            color: #405166;
            padding: 0 10px;
            font-size: 13px;
            font-weight: 850;
        }
        .column-filter-row th {
            background: #ffffff;
            padding: 8px 10px;
        }
        .column-filter-row input,
        .column-filter-row select {
            min-height: 32px;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 12px;
        }
        .checkbox-cell {
            width: 42px;
            text-align: center;
            vertical-align: middle;
        }
        .checkbox-cell input { width: auto; }
        .row-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }
        .builder-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
            background: #fbfdff;
        }
        .builder-summary div {
            border: 1px solid #e0e8f1;
            border-radius: 8px;
            background: #ffffff;
            padding: 12px;
        }
        .builder-summary span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .builder-summary strong {
            display: block;
            margin-top: 5px;
            color: var(--title);
            font-size: 14px;
        }
        .admin-shell.kobo-shell {
            grid-template-columns: 64px 256px minmax(0, 1fr);
            grid-template-rows: 74px minmax(0, 1fr);
            background: #eef0f5;
        }
        .kobo-topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            grid-column: 1 / -1;
            grid-row: 1;
            display: flex;
            align-items: center;
            gap: 28px;
            height: 74px;
            min-width: 0;
            background: #30384b;
            color: #ffffff;
            padding: 0 16px;
            box-shadow: 0 1px 0 rgba(12, 18, 32, 0.18);
            z-index: 4;
        }
        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 216px;
            color: #ffffff;
            text-decoration: none;
        }
        .topbar-brand img { width: 34px; height: 34px; object-fit: contain; }
        .topbar-brand strong { display: block; font-size: 20px; font-weight: 900; line-height: 1; }
        .topbar-brand span { color: #8bd7ff; }
        .topbar-context {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
            color: #f4f7fb;
            font-size: 17px;
            font-weight: 760;
        }
        .topbar-context-icon {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.12);
            color: #8bd7ff;
            font-size: 13px;
            font-weight: 950;
            flex: 0 0 auto;
        }
        .topbar-context-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .topbar-avatar {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            background: #2588c7;
            color: #ffffff;
            font-size: 14px;
            font-weight: 900;
            flex: 0 0 auto;
        }
        .app-rail {
            position: sticky;
            top: 74px;
            grid-column: 1;
            grid-row: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            height: calc(100vh - 74px);
            border-right: 1px solid #dce3ee;
            background: #f8fafc;
            padding: 18px 8px;
        }
        .rail-link {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            color: #667089;
            text-decoration: none;
            font-size: 12px;
            font-weight: 950;
        }
        .rail-link:hover,
        .rail-link.active {
            background: #e6f4ff;
            color: #176fa8;
        }
        .rail-spacer { flex: 1; }
        .project-sidebar {
            top: 74px;
            grid-column: 2;
            grid-row: 2;
            height: calc(100vh - 74px);
            border-right: 1px solid #dce3ee;
            background: #ffffff;
        }
        .project-sidebar .sidebar-footer {
            padding: 12px 14px;
            background: #ffffff;
        }
        .new-project-button {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            margin: 18px 14px 14px;
            border-radius: 7px;
            background: #2698e8;
            color: #ffffff;
            text-decoration: none;
            font-weight: 900;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .project-status-list {
            display: grid;
            gap: 2px;
            padding: 0 14px 12px;
            border-bottom: 1px solid #e5ebf3;
        }
        .project-status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 36px;
            color: #647086;
            text-decoration: none;
            font-size: 14px;
            font-weight: 720;
        }
        .project-count {
            display: inline-grid;
            place-items: center;
            min-width: 28px;
            height: 24px;
            border-radius: 999px;
            background: #edf1f7;
            color: #657086;
            padding: 0 8px;
            font-size: 12px;
            font-weight: 900;
        }
        .project-list-scroll {
            flex: 1;
            overflow: auto;
            padding: 12px 10px 16px;
        }
        .project-nav-link {
            display: block;
            border-left: 4px solid transparent;
            border-radius: 0 7px 7px 0;
            color: #606b82;
            text-decoration: none;
            padding: 8px 10px 8px 12px;
            font-size: 13px;
            line-height: 1.28;
        }
        .project-nav-link:hover,
        .project-nav-link.active {
            background: #edf2f8;
            border-left-color: #55c9d3;
            color: #283449;
        }
        .project-nav-link strong {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 760;
        }
        .project-nav-link span {
            display: block;
            margin-top: 2px;
            color: #7a8498;
            font-size: 11px;
        }
        .project-section-label {
            margin: 14px 10px 8px;
            color: #7c8699;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .system-nav-link {
            display: flex;
            align-items: center;
            min-height: 34px;
            border-radius: 7px;
            color: #5d687d;
            text-decoration: none;
            padding: 0 10px;
            font-size: 13px;
            font-weight: 760;
        }
        .system-nav-link:hover,
        .system-nav-link.active {
            background: #edf2f8;
            color: #283449;
        }
        .kobo-shell .admin-main {
            grid-column: 3;
            grid-row: 2;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 74px);
            background: #eef0f5;
        }
        .kobo-shell .admin-topbar { display: none; }
        .project-tabs {
            position: sticky;
            top: 74px;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 42px;
            min-height: 56px;
            border-bottom: 1px solid #dce3ee;
            background: #ffffff;
        }
        .project-tab {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-height: 56px;
            color: #667089;
            text-decoration: none;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .project-tab.active {
            color: #283449;
        }
        .project-tab.active::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 4px;
            background: #55c9d3;
        }
        .kobo-shell .container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 28px 32px 44px;
        }
        .kobo-shell .workspace-bar {
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 0;
        }
        .kobo-shell .workspace-tabs {
            justify-content: center;
            gap: 40px;
            margin: -8px -32px 24px;
            border-top: 1px solid #dce3ee;
            background: #ffffff;
            padding: 0 22px;
        }
        .kobo-shell .workspace-tab {
            min-height: 50px;
            padding: 0;
            color: #667089;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .kobo-shell .workspace-tab.active {
            color: #283449;
        }
        .kobo-shell .workspace-tab.active::after {
            right: 0;
            left: 0;
            height: 4px;
            background: #55c9d3;
        }
        .workspace-tool-shell {
            display: grid;
            grid-template-columns: 210px minmax(0, 1fr);
            gap: 0;
            min-height: 62vh;
            margin: 0 -32px -44px;
            border-top: 1px solid #dce3ee;
        }
        .workspace-tool-nav {
            border-right: 1px solid #dce3ee;
            background: #ffffff;
            padding: 18px 0;
        }
        .workspace-tool-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 46px;
            border-left: 4px solid transparent;
            color: #667089;
            text-decoration: none;
            padding: 0 18px;
            font-size: 15px;
            font-weight: 820;
        }
        .workspace-tool-nav a:hover,
        .workspace-tool-nav a.active {
            border-left-color: #55c9d3;
            background: #f8fbfe;
            color: #283449;
        }
        .workspace-tool-icon {
            display: inline-grid;
            place-items: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: #eef3f8;
            color: #526179;
            font-size: 11px;
            font-weight: 950;
        }
        .workspace-tool-content {
            min-width: 0;
            padding: 26px 28px 44px;
        }
        .kobo-shell .panel {
            border-radius: 0;
            box-shadow: none;
        }
        .kobo-shell .panel-head {
            background: #ffffff;
        }
        .kobo-shell th {
            background: #e9ebf2;
            color: #3d4659;
            font-size: 13px;
            letter-spacing: 0;
            text-transform: none;
        }
        .kobo-shell tbody tr:hover {
            background: #f7fbff;
        }
        .settings-card {
            border: 1px solid #dce3ee;
            background: #ffffff;
            padding: 24px;
        }
        .service-register-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }
        .service-form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 14px;
        }
        .current-version-panel,
        .collect-data-panel {
            border: 1px solid #dce3ee;
            background: #ffffff;
            margin-bottom: 26px;
        }
        .current-version-row,
        .collect-data-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 22px 24px;
            border-bottom: 1px solid #e5ebf3;
        }
        .collect-data-row:last-child,
        .current-version-row:last-child {
            border-bottom: 0;
        }
        .version-title {
            color: #283449;
            font-size: 15px;
            font-weight: 900;
        }
        .version-title strong {
            margin-right: 8px;
        }
        form.inline { display: inline; }
        @media (max-width: 1100px) {
            .filter-grid, .project-toolbar .filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .metric-strip, .summary-grid, .template-grid, .builder-summary, .builder-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .project-card-main { grid-template-columns: 1fr; }
            .project-stats { min-width: 0; }
        }
        @media (max-width: 900px) {
            .admin-shell { grid-template-columns: 1fr; }
            .admin-shell.kobo-shell {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto auto;
            }
            .kobo-topbar,
            .app-rail,
            .project-sidebar,
            .kobo-shell .admin-main {
                grid-column: 1;
                grid-row: auto;
            }
            .kobo-topbar {
                position: static;
                height: auto;
                flex-wrap: wrap;
                gap: 12px;
                min-height: 74px;
                padding: 14px;
            }
            .topbar-brand { min-width: 0; }
            .topbar-context { order: 3; flex-basis: 100%; font-size: 15px; }
            .app-rail {
                position: static;
                flex-direction: row;
                justify-content: flex-start;
                height: auto;
                border-right: 0;
                border-bottom: 1px solid #dce3ee;
                overflow-x: auto;
                padding: 10px 12px;
            }
            .rail-spacer { display: none; }
            .project-sidebar {
                position: static;
                height: auto;
            }
            .project-status-list { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .project-list-scroll { max-height: 220px; }
            .project-tabs {
                position: static;
                justify-content: flex-start;
                gap: 28px;
                overflow-x: auto;
                padding: 0 18px;
            }
            .kobo-shell .container { padding: 18px; }
            .workspace-tool-shell {
                grid-template-columns: 1fr;
                margin: 0 -18px -18px;
            }
            .workspace-tool-nav {
                display: flex;
                overflow-x: auto;
                border-right: 0;
                border-bottom: 1px solid #dce3ee;
                padding: 0;
            }
            .workspace-tool-nav a {
                min-width: max-content;
                border-left: 0;
                border-bottom: 4px solid transparent;
            }
            .workspace-tool-nav a:hover,
            .workspace-tool-nav a.active {
                border-left-color: transparent;
                border-bottom-color: #55c9d3;
            }
            .workspace-tool-content { padding: 20px 18px 32px; }
            .service-form-grid { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; }
            .sidebar-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; overflow: visible; }
            .nav-group { margin: 0; }
            .admin-topbar { display: none; }
            .container { padding: 18px; }
            .grid, .filter-grid, .project-toolbar .filters, .builder-hero { grid-template-columns: 1fr; }
            .workspace-bar, .data-toolbar, .project-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .workspace-meta { justify-content: flex-start; }
            .topbar-search { width: 100%; }
        }
        @media (max-width: 560px) {
            .sidebar-nav, .metric-strip, .summary-grid, .template-grid, .builder-summary, .project-stats, .builder-card-grid { grid-template-columns: 1fr; }
            .builder-question-main { grid-template-columns: 1fr; }
            .builder-question-include { justify-content: flex-start; padding: 14px 18px 0; border-right: 0; background: #fff; }
            .builder-question-head { flex-direction: column; }
            .header { align-items: flex-start; flex-direction: column; }
            th, td { padding: 10px; }
        }
    </style>
</head>
<body>
@if($showAdminShell)
    <div class="admin-shell kobo-shell">
        <header class="kobo-topbar">
            <a class="topbar-brand" href="{{ route('admin.projects.index') }}" aria-label="BorderReach projects">
                <img src="{{ asset('images/borderreach-mark.svg') }}" alt="">
                <strong>Border<span>Reach</span></strong>
            </a>
            <div class="topbar-context" aria-label="Current workspace">
                <span class="topbar-context-icon">BR</span>
                <span class="topbar-context-title">{{ $currentWorkspaceTitle }}</span>
            </div>
            <span class="topbar-avatar" title="{{ auth()->user()?->email }}">{{ $userInitials ?: 'BR' }}</span>
        </header>

        <aside class="app-rail" aria-label="Primary workspace navigation">
            <a class="rail-link {{ request()->routeIs('admin.projects.*', 'admin.forms.*') ? 'active' : '' }}" href="{{ route('admin.projects.index') }}" title="Projects">P</a>
            <a class="rail-link {{ request()->routeIs('admin.submissions.*') ? 'active' : '' }}" href="{{ route('admin.submissions.index') }}" title="Data">D</a>
            <a class="rail-link {{ request()->routeIs('admin.map.*') ? 'active' : '' }}" href="{{ route('admin.map.index') }}" title="Map">M</a>
            <a class="rail-link {{ request()->routeIs('admin.dashboard.*') ? 'active' : '' }}" href="{{ route('admin.dashboard.index') }}" title="Reports">R</a>
            <a class="rail-link {{ request()->routeIs('admin.webhooks.*', 'admin.webhook-deliveries.*') ? 'active' : '' }}" href="{{ route('admin.webhooks.index') }}" title="REST Services">API</a>
            <span class="rail-spacer"></span>
            <a class="rail-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}" title="Profile">{{ mb_substr($userInitials ?: 'U', 0, 1) }}</a>
        </aside>

        <aside class="sidebar project-sidebar" aria-label="Projects and administration">
            <a class="new-project-button" href="{{ route('admin.forms.builder') }}">New</a>

            <nav class="project-status-list" aria-label="Project status filters">
                <a class="project-status-item" href="{{ route('admin.forms.index') }}">
                    <span>Deployed</span>
                    <span class="project-count">{{ $shellCounts['deployed'] }}</span>
                </a>
                <a class="project-status-item" href="{{ route('admin.forms.index', ['status' => 'draft']) }}">
                    <span>Draft</span>
                    <span class="project-count">{{ $shellCounts['draft'] }}</span>
                </a>
                <a class="project-status-item" href="{{ route('admin.forms.index', ['status' => 'archived']) }}">
                    <span>Archived</span>
                    <span class="project-count">{{ $shellCounts['archived'] }}</span>
                </a>
            </nav>

            <div class="project-list-scroll">
                @forelse($shellProjects as $shellProject)
                    <a class="project-nav-link {{ $currentRouteFormId === $shellProject->id ? 'active' : '' }}" href="{{ route('admin.forms.show', $shellProject) }}">
                        <strong>{{ $shellProject->title }}</strong>
                        <span>{{ $shellProject->country?->name ?? $shellProject->country_code }} / {{ $shellProject->moduleLabel() }}</span>
                    </a>
                @empty
                    <a class="project-nav-link" href="{{ route('admin.forms.builder') }}">
                        <strong>Create your first project</strong>
                        <span>Build a standards-based border report</span>
                    </a>
                @endforelse

                <div class="project-section-label">Administration</div>
                @foreach($navGroups['Configuration'] as $item)
                    @php($isActive = collect((array) $item['active'])->contains(fn ($active) => request()->routeIs($active)))
                    <a class="system-nav-link {{ $isActive ? 'active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
                @foreach($navGroups['Administration'] as $item)
                    @php($isActive = collect((array) $item['active'])->contains(fn ($active) => request()->routeIs($active)))
                    <a class="system-nav-link {{ $isActive ? 'active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
            </div>

            <div class="sidebar-footer">
                <div class="user-meta">
                    <strong>{{ auth()->user()?->name }}</strong>
                    <span>{{ auth()->user()?->email }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button secondary" style="width:100%;">Sign out</button>
                </form>
            </div>
        </aside>

        <main class="admin-main">
            <nav class="project-tabs" aria-label="Project workspace tabs">
                @foreach($projectTabs as $tab)
                    @php($isActive = collect((array) $tab['active'])->contains(fn ($active) => request()->routeIs($active)))
                    <a class="project-tab {{ $isActive ? 'active' : '' }}" href="{{ route($tab['route']) }}">{{ $tab['label'] }}</a>
                @endforeach
            </nav>
            <div class="container">
@else
    <div class="guest-shell">
        <div class="container">
@endif

@if(session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="error">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@yield('content')

        </div>
@if($showAdminShell)
        </main>
    </div>
@else
    </div>
@endif
</body>
</html>
