@extends('layouts.admin')

@section('content')
    <div class="workspace-bar">
        <div>
            <div class="workspace-crumb">BorderReach / Workspace</div>
            <h1 class="workspace-title">Projects</h1>
            <p class="subtitle">Create, publish, and review standardized border reporting projects.</p>
        </div>
        <div class="workspace-meta">
            <span class="tag">{{ number_format($summary['total']) }} projects</span>
            <span class="tag published">{{ number_format($summary['published']) }} published</span>
            <a class="tool-button primary" href="{{ route('admin.forms.builder') }}">New Project</a>
        </div>
    </div>

    <div class="project-toolbar">
        <form method="GET" action="{{ route('admin.projects.index') }}" class="filters">
            <div>
                <label for="country_code">Country</label>
                <select id="country_code" name="country_code">
                    <option value="">All countries</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->code }}" @selected(($filters['country_code'] ?? '') === $country->code)>
                            {{ $country->name }} ({{ $country->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reporting_module">Report Type</label>
                <select id="reporting_module" name="reporting_module">
                    <option value="">All report types</option>
                    @foreach($moduleLabels as $module => $label)
                        <option value="{{ $module }}" @selected(($filters['reporting_module'] ?? '') === $module)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="actions">
                <button type="submit">Apply</button>
                <a class="button light" href="{{ route('admin.projects.index') }}">Clear</a>
            </div>
        </form>
        <div class="actions">
            <a class="button" href="#template-library">Templates</a>
            <a class="button light" href="{{ route('admin.forms.create') }}">Upload XLSForm</a>
        </div>
    </div>

    <div class="section-heading-row">
        <div>
            <h2>Operational Projects</h2>
            <p>Editable projects that can be versioned, published, synced, and analyzed.</p>
        </div>
    </div>

    <div class="project-list">
        @forelse($forms as $form)
            @php
                $stats = $projectStats->get(strtoupper($form->country_code).'|'.$form->form_id);
                $latestSync = $stats?->latest_received_at
                    ? \Illuminate\Support\Carbon::parse($stats->latest_received_at)->diffForHumans()
                    : 'No synced records';
                $moduleInitials = collect(preg_split('/\s+/', preg_replace('/[^A-Za-z ]/', ' ', $form->moduleLabel())) ?: [])
                    ->filter()
                    ->take(2)
                    ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
                    ->implode('');
            @endphp
            <article class="project-card">
                <div class="project-card-main">
                    <div>
                        <div class="project-name">
                            <span class="project-icon">{{ $moduleInitials ?: 'BR' }}</span>
                            <div>
                                <a href="{{ route('admin.forms.show', $form) }}">{{ $form->title }}</a>
                                <div class="record-muted mono inline">{{ $form->form_id }}</div>
                            </div>
                        </div>
                        <p class="project-description">
                            {{ $form->moduleLabel() }} project for {{ $form->country?->name ?? $form->country_code }}. Publish controlled versions and review synced JSON records.
                        </p>
                        <div class="project-meta-row">
                            <span class="tag">{{ $form->country?->name ?? $form->country_code }}</span>
                            <span class="tag">{{ $form->moduleLabel() }}</span>
                            <span class="tag {{ $form->publishedVersion ? 'published' : 'draft' }}">
                                {{ $form->publishedVersion ? 'Published v'.$form->publishedVersion->version : 'Draft only' }}
                            </span>
                            <span class="tag">{{ $form->versions->count() }} versions</span>
                        </div>
                    </div>
                    <div class="project-stats" aria-label="Project activity">
                        <div class="project-stat"><span>Records</span><strong>{{ number_format((int) ($stats?->total ?? 0)) }}</strong></div>
                        <div class="project-stat"><span>Accepted</span><strong>{{ number_format((int) ($stats?->accepted ?? 0)) }}</strong></div>
                        <div class="project-stat"><span>Latest sync</span><strong style="font-size:13px;">{{ $latestSync }}</strong></div>
                    </div>
                </div>
                <div class="project-actions" aria-label="Project actions">
                    <a class="tool-button primary" href="{{ route('admin.forms.show', $form) }}">Summary</a>
                    <a class="tool-button" href="{{ route('admin.forms.builder.edit', $form) }}">Form builder</a>
                    <a class="tool-button" href="{{ route('admin.submissions.index', ['country_code' => $form->country_code, 'form_id' => $form->form_id]) }}">Data</a>
                    <a class="tool-button" href="{{ route('admin.map.index', ['country_code' => $form->country_code]) }}">Map</a>
                    <form method="POST" action="{{ route('admin.forms.destroy', $form) }}" onsubmit="return confirm('Delete this project? Projects with synced records are protected and will not be deleted.');">
                        @csrf
                        @method('DELETE')
                        <button class="tool-button danger" type="submit">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">No projects yet</h2>
                        <p class="panel-subtitle">Start with a standards-backed template or upload an XLSForm.</p>
                    </div>
                    <div class="actions">
                        <a class="button" href="{{ route('admin.forms.builder') }}">Build Project</a>
                        <a class="button light" href="{{ route('admin.forms.create') }}">Upload XLSForm</a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{ $forms->links() }}

    <section id="template-library" class="panel" style="margin-top:22px;">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Template Library</h2>
                <p class="panel-subtitle">Copy a protected baseline, then adapt it in the form builder.</p>
            </div>
            <span class="tag">{{ number_format($templateForms->count()) }} templates</span>
        </div>
        <div class="template-grid template-grid-wide" style="padding:18px;">
            @forelse($templateForms as $template)
                @php
                    $summaryData = $template->template_summary ?: [];
                    $sections = collect($summaryData['sections'] ?? []);
                    $sampleFields = $sections
                        ->flatMap(fn ($section) => $section['fields'] ?? [])
                        ->take(4)
                        ->values();
                @endphp
                <article class="template-card template-card-detailed">
                    <div class="template-card-head">
                        <div>
                            <span class="template-kicker">{{ $template->moduleLabel() }}</span>
                            <h3>{{ $template->title }}</h3>
                        </div>
                        <span class="tag">{{ number_format((int) ($summaryData['field_count'] ?? 0)) }} fields</span>
                    </div>
                    <p>{{ $template->template_description ?: 'Standards-backed report template ready to copy into an operational project.' }}</p>
                    <div class="template-standard">{{ $template->publishedVersion?->compiled_schema['standardReference'] ?? \App\Models\DynamicForm::standardReferenceForModule($template->reporting_module) }}</div>

                    @if($sampleFields->isNotEmpty())
                        <div class="template-field-chips" aria-label="Example fields">
                            @foreach($sampleFields as $field)
                                <span>{{ $field['label'] }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="template-actions">
                        <form method="POST" action="{{ route('admin.forms.templates.clone', $template) }}">
                            @csrf
                            <input type="hidden" name="country_code" value="{{ $filters['country_code'] ?: $template->country_code }}">
                            <button type="submit">Clone Template</button>
                        </form>
                        <a class="button light" href="{{ route('admin.forms.show', $template) }}">Preview</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <h3>No templates loaded</h3>
                    <p>Run the template seeder to load the ICAO, WCO, WHO, and security starter templates.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
