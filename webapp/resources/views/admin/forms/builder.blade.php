@extends('layouts.admin')

@php
    $savedRows = old('fields', $rows);
    $rowsForDisplay = collect($savedRows)
        ->merge(array_fill(0, 5, ['id' => '', 'type' => 'text', 'label' => '', 'hint' => '', 'required' => false, 'options' => '']))
        ->values();
    $includedRowCount = collect($savedRows)->filter(function ($row) {
        return trim((string) ($row['id'] ?? '')) !== '' || trim((string) ($row['label'] ?? '')) !== '';
    })->count();
    $fieldPurpose = function (array $row): string {
        $hint = trim((string) ($row['hint'] ?? ''));
        if ($hint !== '') {
            return $hint;
        }

        $id = strtolower((string) ($row['id'] ?? ''));
        $label = trim((string) ($row['label'] ?? $row['id'] ?? 'This field'));

        if (($row['type'] ?? '') === 'note') {
            return 'Starts a section in the mobile flow so officers complete related questions together.';
        }
        if (str_contains($id, 'mrz')) {
            return 'Supports machine-readable-zone capture, validation, and comparison with visible document data.';
        }
        if (str_contains($id, 'document') || str_contains($id, 'passport')) {
            return 'Records travel document details needed for identity, validity, and inspection review.';
        }
        if (str_contains($id, 'customs') || str_contains($id, 'goods') || str_contains($id, 'duty')) {
            return 'Captures declaration, inspection, duty, and enforcement information for customs workflows.';
        }
        if (str_contains($id, 'health') || str_contains($id, 'screening') || str_contains($id, 'symptom')) {
            return 'Captures point-of-entry health screening, public health action, and referral details.';
        }
        if (str_contains($id, 'incident') || str_contains($id, 'security')) {
            return 'Captures incident facts needed for escalation, referral, and accountable follow-up.';
        }
        if (str_contains($id, 'location') || str_contains($id, 'border') || str_contains($id, 'post')) {
            return 'Links the report to a post, route, or location for map review and supervision.';
        }

        return "Captures {$label} for the structured report and downstream review.";
    };
@endphp

@section('content')
    <div class="workspace-bar">
        <div>
            <div class="workspace-crumb">BorderReach / Form project</div>
            <h1 class="workspace-title">{{ $form ? 'Edit Form Builder' : 'Build Form' }}</h1>
            <p class="subtitle">{{ $form ? 'Create a controlled draft from the current published schema.' : 'Create a versioned border report type and publish it to assigned field teams.' }}</p>
        </div>
        <div class="workspace-meta">
            <span class="tag">{{ $includedRowCount }} fields</span>
            <span class="tag">{{ $form ? 'Draft version' : 'New form' }}</span>
            <a class="tool-button" href="{{ $form ? route('admin.forms.show', $form) : route('admin.forms.index') }}">Back</a>
        </div>
    </div>

    @unless($form)
        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Starter Templates</h2>
                    <p class="panel-subtitle">Choose a standards-based baseline, then adapt the fields for the operational workflow.</p>
                </div>
            </div>
            <div class="template-grid" style="padding:18px;">
                @foreach($templates as $key => $template)
                    <div class="template-card">
                        <strong>{{ $template['name'] }}</strong>
                        <p class="field-help" style="min-height:54px;">{{ $template['description'] }}</p>
                        <div class="mono" style="margin:12px 0;">{{ $template['form_id'] }}</div>
                        <a class="button {{ ($selectedTemplate['form_id'] ?? '') === $template['form_id'] ? 'secondary' : 'light' }}" href="{{ route('admin.forms.builder', ['template' => $key]) }}">
                            {{ ($selectedTemplate['form_id'] ?? '') === $template['form_id'] ? 'Selected' : 'Use Template' }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endunless

    <form method="POST" action="{{ $action }}">
        @csrf

        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Project settings</h2>
                    <p class="panel-subtitle">Country tenant, module, standard baseline, and mobile display title.</p>
                </div>
                <span class="tag">{{ $form ? 'Draft version' : 'New form' }}</span>
            </div>
            <div class="builder-summary">
                <div><span>Standard</span><strong>{{ old('standard_reference', $standardReference) ?: 'Not set' }}</strong></div>
                <div><span>Sync scope</span><strong>Country tenant and assigned officers</strong></div>
                <div><span>Mobile flow</span><strong>Step-based offline form</strong></div>
                <div><span>Storage</span><strong>Submission answers saved as JSON</strong></div>
            </div>
            <div class="grid" style="padding:18px;">
                <div>
                    <label for="country_code">Country Profile</label>
                    @if($form)
                        <input type="hidden" name="country_code" value="{{ $form->country_code }}">
                        <input id="country_code" type="text" value="{{ $form->country?->name ?? $form->country_code }}" readonly>
                    @else
                        <select id="country_code" name="country_code" required>
                            @foreach($countries as $country)
                                <option value="{{ $country->code }}" @selected(old('country_code', 'SLE') === $country->code)>{{ $country->name }} ({{ $country->code }})</option>
                            @endforeach
                        </select>
                    @endif
                    <div class="field-help">Forms sync only to officers assigned to this country profile.</div>
                </div>
                <div>
                    <label for="form_id">Form ID</label>
                    <input id="form_id" type="text" name="form_id" value="{{ old('form_id', $form?->form_id ?? ($selectedTemplate['form_id'] ?? '')) }}" {{ $form ? 'readonly' : '' }} required>
                    <div class="field-help">Stable sync key used by mobile devices. Use letters, numbers, dots, dashes, or underscores.</div>
                </div>
                <div>
                    <label for="reporting_module">Reporting Module</label>
                    <select id="reporting_module" name="reporting_module" required>
                        @foreach($moduleLabels as $module => $label)
                            <option value="{{ $module }}" @selected(old('reporting_module', $form?->reporting_module ?? ($selectedTemplate['reporting_module'] ?? 'immigration')) === $module)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="field-help">Used for mobile grouping, dashboard filtering, and exports.</div>
                </div>
                <div>
                    <label for="standard_reference">Standards Baseline</label>
                    <input id="standard_reference" type="text" name="standard_reference" value="{{ old('standard_reference', $standardReference) }}" maxlength="255">
                    <div class="field-help">Stored in the mobile schema for audit and interoperability review.</div>
                </div>
                <div>
                    <label for="title">Form Title</label>
                    <input id="title" type="text" name="title" value="{{ old('title', $form?->title ?? ($selectedTemplate['title'] ?? '')) }}" required>
                    <div class="field-help">Shown to officers in the Android app.</div>
                </div>
            </div>
        </div>

        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Form questions</h2>
                    <p class="panel-subtitle">Keep the full template selected, or clear fields that do not apply to this border workflow.</p>
                </div>
                <div class="builder-controls">
                    <button type="button" class="button light" onclick="document.querySelectorAll('.include-field').forEach((field) => field.checked = true)">Select All</button>
                    <button type="button" class="button light" onclick="document.querySelectorAll('.include-field[data-required=&quot;0&quot;]').forEach((field) => field.checked = false)">Clear Optional</button>
                    <label style="display: flex; align-items: center; gap: 8px; margin: 0;">
                        <input type="checkbox" name="publish" value="1" {{ old('publish') ? 'checked' : '' }}>
                        Publish now
                    </label>
                </div>
            </div>
            <div class="data-toolbar">
                <div class="toolbar-group">
                    <span class="selected-count">{{ $includedRowCount }} configured fields</span>
                    <button type="button" class="tool-button">Question library</button>
                    <button type="button" class="tool-button">Preview mobile flow</button>
                </div>
                <div class="toolbar-group">
                    <span class="tag">Versioned publishing</span>
                </div>
            </div>

            <div class="builder-question-list">
                @foreach($rowsForDisplay as $index => $row)
                    @php
                        $hasFieldContent = trim((string) ($row['id'] ?? '')) !== ''
                            || trim((string) ($row['label'] ?? '')) !== ''
                            || trim((string) ($row['hint'] ?? '')) !== ''
                            || trim((string) ($row['options'] ?? '')) !== '';
                        $included = array_key_exists('include', $row) ? (bool) $row['include'] : $hasFieldContent;
                        $rowType = $row['type'] ?? 'text';
                        $rowLabel = trim((string) ($row['label'] ?? '')) ?: 'New Question';
                        $purpose = $fieldPurpose($row);
                    @endphp
                    <article class="builder-question-card {{ $rowType === 'note' ? 'builder-section-card' : '' }}">
                        <div class="builder-question-main">
                            <div class="builder-question-include">
                                <input type="hidden" name="fields[{{ $index }}][include]" value="0">
                                <input class="include-field" data-required="{{ !empty($row['required']) ? '1' : '0' }}" type="checkbox" name="fields[{{ $index }}][include]" value="1" @checked($included) aria-label="Include {{ $rowLabel }}">
                            </div>
                            <div class="builder-question-body">
                                <div class="builder-question-head">
                                    <div>
                                        <span class="template-kicker">{{ $rowType === 'note' ? 'Section' : 'Question' }} {{ $index + 1 }}</span>
                                        <h3>{{ $rowLabel }}</h3>
                                    </div>
                                    <div class="builder-question-tags">
                                        <span class="tag">{{ $rowType }}</span>
                                        <label class="tag required-toggle">
                                            <input type="checkbox" name="fields[{{ $index }}][required]" value="1" @checked(!empty($row['required']))>
                                            Required
                                        </label>
                                    </div>
                                </div>
                                <p class="builder-question-purpose">{{ $purpose }}</p>

                                <div class="builder-card-grid">
                                    <div>
                                        <label>Field ID</label>
                                        <input type="text" name="fields[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}" placeholder="passport_number">
                                        <div class="field-help">Stable JSON key used in exports, maps, filters, and API payloads.</div>
                                    </div>
                                    <div>
                                        <label>Type</label>
                                        <select name="fields[{{ $index }}][type]">
                                            @foreach($fieldTypes as $type)
                                                <option value="{{ $type }}" @selected($rowType === $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label>Label</label>
                                        <input type="text" name="fields[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Passport Number">
                                    </div>
                                    <div>
                                        <label>Officer guidance</label>
                                        <input type="text" name="fields[{{ $index }}][hint]" value="{{ $row['hint'] ?? '' }}" placeholder="Short help text shown on mobile">
                                    </div>
                                </div>

                                <details class="builder-options-panel" @if(in_array($rowType, ['select_one', 'select_multiple'], true)) open @endif>
                                    <summary>Options and catalog source</summary>
                                    <div class="builder-card-grid">
                                        <div>
                                            <label>Option source</label>
                                            <select name="fields[{{ $index }}][option_source]">
                                                @foreach($optionSources as $source => $label)
                                                    <option value="{{ $source }}" @selected(($row['option_source'] ?? 'manual') === $source)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <div class="field-help">Use uploaded catalogs for frequent locations and manual lists for fixed answers.</div>
                                        </div>
                                        <div>
                                            <label>Manual options</label>
                                            <textarea name="fields[{{ $index }}][options]" placeholder="entry|Entry&#10;exit|Exit">{{ $row['options'] ?? '' }}</textarea>
                                            <div class="field-help">One option per line as value|Label.</div>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="actions">
            <button type="submit">Save Version</button>
            <a class="button light" href="{{ route('admin.forms.create') }}">Import XLSForm Instead</a>
        </div>
    </form>
@endsection
