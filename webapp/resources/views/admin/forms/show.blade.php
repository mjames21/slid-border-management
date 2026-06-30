@extends('layouts.admin')

@section('content')
    @php
        $currentVersion = $form->publishedVersion ?: $form->versions->sortByDesc('version')->first();
        $currentMetadata = $currentVersion?->source_metadata ?? [];
        $currentSchema = $currentVersion?->compiled_schema ?? [];
        $currentFields = $currentSchema['fields'] ?? [];
    @endphp

    <div class="header">
        <div>
            <h1 class="title">{{ $form->title }}</h1>
            <p class="subtitle mono">{{ $form->country?->name ?? $form->country_code }} / {{ $form->moduleLabel() }} / {{ $form->form_id }}</p>
        </div>
        <div class="actions">
            @if($form->is_template)
                <form method="POST" action="{{ route('admin.forms.templates.clone', $form) }}">
                    @csrf
                    <button type="submit">Clone Template</button>
                </form>
            @else
                <a class="button" href="{{ route('admin.forms.builder.edit', $form) }}">Edit in Builder</a>
            @endif
            <a class="button light" href="{{ route('admin.forms.index') }}">Back</a>
        </div>
    </div>

    @if($form->is_template)
        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Protected Template</h2>
                    <p class="panel-subtitle">{{ $form->template_description ?: 'This standards baseline is read-only. Clone it into an operational project before editing or publishing to devices.' }}</p>
                </div>
                <span class="tag">Read-only library item</span>
            </div>
            @php($templateSections = collect($form->template_summary['sections'] ?? []))
            @if($templateSections->isNotEmpty())
                <div class="template-grid template-grid-wide" style="padding:18px;">
                    @foreach($templateSections->take(4) as $section)
                        <div class="template-card">
                            <strong>{{ $section['title'] }}</strong>
                            <p class="field-help">{{ $section['purpose'] }}</p>
                            <div class="template-field-chips" style="margin-top:10px;">
                                @foreach(collect($section['fields'] ?? [])->take(4) as $field)
                                    <span>{{ $field['label'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="current-version-panel">
        <div class="current-version-row">
            <div>
                <div class="version-title">
                    <strong>Current version</strong>
                    @if($currentVersion)
                        v{{ $currentVersion->version }}
                    @else
                        No version imported
                    @endif
                </div>
                <p class="panel-subtitle">
                    @if($currentVersion)
                        Last modified {{ $currentVersion->updated_at?->format('M j, Y') }} · {{ count($currentFields) }} mobile fields
                    @else
                        Create or import a project form before publishing to mobile devices.
                    @endif
                </p>
            </div>
            @if($form->is_template)
                <span class="tag">Template baseline</span>
            @elseif($currentVersion && !$currentVersion->is_published)
                <form method="POST" action="{{ route('admin.forms.versions.publish', [$form, $currentVersion->version]) }}">
                    @csrf
                    <button type="submit" class="button blue">Deploy</button>
                </form>
            @elseif($currentVersion)
                <span class="tag published">Deployed</span>
            @else
                <a class="tool-button primary" href="{{ route('admin.forms.builder.edit', $form) }}">Create version</a>
            @endif
        </div>
        <div class="current-version-row">
            <div>
                <div class="version-title">Languages</div>
                <p class="panel-subtitle">Default labels are available for mobile collection. Additional translations can be added in the form builder.</p>
            </div>
            @if($form->is_template)
                <form method="POST" action="{{ route('admin.forms.templates.clone', $form) }}">
                    @csrf
                    <button type="submit" class="tool-button">Clone to edit</button>
                </form>
            @else
                <a class="tool-button" href="{{ route('admin.forms.builder.edit', $form) }}">Manage</a>
            @endif
        </div>
    </div>

    <div class="collect-data-panel">
        <div class="collect-data-row">
            <div>
                <div class="version-title">Collect data</div>
                <p class="panel-subtitle">Collect from Android or open the same published form in a browser for authenticated officers assigned to this tenant and border post.</p>
            </div>
            <div class="actions">
                @if(! $form->is_template && $form->publishedVersion)
                    @php($webCollectUrl = route('collect.forms.show', $form))
                    <a class="tool-button primary" href="{{ $webCollectUrl }}" target="_blank" rel="noopener">Open web form</a>
                    <button type="button" class="tool-button" data-copy-url="{{ $webCollectUrl }}">Copy link</button>
                @endif
                <a class="tool-button" href="{{ route('admin.users.index') }}">Manage officers</a>
                <a class="tool-button" href="{{ route('admin.webhooks.index') }}">REST Services</a>
            </div>
        </div>
        @if($form->is_template)
            <p class="panel-subtitle">Templates are not opened directly. Clone this template, publish it, then collect reports from the copy.</p>
        @elseif(! $form->publishedVersion)
            <p class="panel-subtitle">Publish a version before opening Android or browser collection.</p>
        @endif
    </div>

    @foreach($form->versions as $version)
        <div class="card">
            <div class="header">
                <div>
                    <h2 class="title" style="font-size: 20px;">Version {{ $version->version }}</h2>
                    <p class="subtitle">
                        <span class="tag {{ $version->is_published ? 'published' : 'draft' }}">{{ $version->is_published ? 'Published' : 'Draft' }}</span>
                    </p>
                </div>
                @unless($version->is_published || $form->is_template)
                    <form method="POST" action="{{ route('admin.forms.versions.publish', [$form, $version->version]) }}">
                        @csrf
                        <button type="submit">Publish</button>
                    </form>
                @endunless
            </div>

            @php($metadata = $version->source_metadata ?? [])
            @php($warnings = $metadata['warnings'] ?? [])
            @php($ignoredRows = $metadata['ignoredRows'] ?? [])
            @php($unsupportedRows = $metadata['unsupportedRows'] ?? [])
            @php($schema = $version->compiled_schema)
            @php($fields = $schema['fields'] ?? [])

            <h3>Review Summary</h3>
            <div class="grid">
                <div><label>Standards baseline</label><div class="mono">{{ $schema['standardReference'] ?? \App\Models\DynamicForm::standardReferenceForModule($form->reporting_module) }}</div></div>
                <div><label>Reporting module</label><div class="mono">{{ $form->moduleLabel() }}</div></div>
                <div><label>Mobile fields included</label><div class="mono">{{ $metadata['fieldCount'] ?? count($fields) }}</div></div>
                <div><label>Choice lists included</label><div class="mono">{{ $metadata['choiceListCount'] ?? count($schema['choiceLists'] ?? []) }}</div></div>
                <div><label>Layout or metadata rows</label><div class="mono">{{ count($ignoredRows) }}</div></div>
                <div><label>Unsupported rows</label><div class="mono">{{ count($unsupportedRows) }}</div></div>
            </div>

            @if($warnings)
                <h3>Needs Attention</h3>
                <ul>
                    @foreach($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif

            @if($unsupportedRows)
                <h3>Not Included in Mobile Runtime</h3>
                <p class="subtitle">These XLSForm fields are outside the current FSD-supported mobile subset.</p>
                <table>
                    <thead><tr><th>Row</th><th>Type</th><th>Name</th><th>Reason</th></tr></thead>
                    <tbody>
                    @foreach($unsupportedRows as $row)
                        <tr>
                            <td>{{ $row['row'] }}</td>
                            <td class="mono">{{ $row['type'] }}</td>
                            <td class="mono">{{ $row['name'] }}</td>
                            <td>{{ $row['reason'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            @if($ignoredRows)
                <details style="margin-top: 18px;">
                    <summary><strong>Layout and metadata rows not rendered as questions</strong></summary>
                    <table style="margin-top: 12px;">
                        <thead><tr><th>Row</th><th>Type</th><th>Name</th><th>Why this is OK</th></tr></thead>
                        <tbody>
                        @foreach($ignoredRows as $row)
                            <tr>
                                <td>{{ $row['row'] }}</td>
                                <td class="mono">{{ $row['type'] }}</td>
                                <td class="mono">{{ $row['name'] }}</td>
                                <td>{{ $row['reason'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>
            @endif

            <h3>Mobile Fields</h3>
            <table>
                <thead><tr><th>Field</th><th>Type</th><th>Label</th><th>Required</th></tr></thead>
                <tbody>
                @foreach($fields as $field)
                    <tr>
                        <td class="mono">{{ $field['id'] ?? '' }}</td>
                        <td>{{ $field['type'] ?? '' }}</td>
                        <td>{{ $field['label'] ?? '' }}</td>
                        <td>{{ !empty($field['required']) ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <details style="margin-top: 18px;">
                <summary><strong>Compiled runtime JSON</strong></summary>
                <pre class="mono">{{ json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </div>
@endforeach

<script>
    document.querySelectorAll('[data-copy-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copyUrl);
                const original = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(() => button.textContent = original, 1600);
            } catch (error) {
                window.prompt('Copy web form link', button.dataset.copyUrl);
            }
        });
    });
</script>
@endsection
