@php
    $fields = $schema['fields'] ?? [];
    $choiceLists = $schema['choiceLists'] ?? [];
    $choiceOptions = static function (array $field) use ($choiceLists): array {
        $listName = $field['listName'] ?? null;

        return $field['options'] ?? ($listName ? ($choiceLists[$listName] ?? []) : []);
    };
    $backUrl = auth()->user()?->is_admin ? route('admin.forms.show', $form) : route('dashboard');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $schema['title'] ?? $form->title }} | BorderReach</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        :root {
            --green: #4da34f;
            --green-dark: #177245;
            --navy: #2f3a4e;
            --ink: #26374a;
            --muted: #657086;
            --line: #dce3ee;
            --wash: #eef0f5;
            --paper: #ffffff;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--wash);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.45;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 74px;
            background: var(--navy);
            color: #ffffff;
            padding: 14px 28px;
        }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 850; }
        .brand img { width: 42px; height: 42px; object-fit: contain; }
        .topbar a { color: #ffffff; text-decoration: none; font-weight: 760; }
        .shell { max-width: 900px; margin: 34px auto; padding: 0 20px 60px; }
        .paper {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--paper);
            padding: 34px;
            box-shadow: 0 18px 45px rgba(31, 48, 74, 0.08);
        }
        h1 { margin: 0; color: var(--green-dark); font-size: 30px; line-height: 1.12; text-align: center; }
        .meta { margin: 12px 0 28px; color: var(--muted); text-align: center; font-size: 14px; }
        .status, .error {
            margin-bottom: 20px;
            border-radius: 8px;
            padding: 12px 14px;
            font-weight: 720;
        }
        .status { border: 1px solid #b8dffc; background: #eff8ff; color: #155e91; }
        .error { border: 1px solid #fecaca; background: #fff1f2; color: var(--danger); }
        .location-banner {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            margin-bottom: 22px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fbfe;
            padding: 12px 14px;
            color: var(--muted);
            font-size: 13px;
        }
        .field { margin-bottom: 24px; }
        label, .legend {
            display: block;
            margin-bottom: 8px;
            color: #303a4e;
            font-size: 16px;
            font-weight: 850;
        }
        .required { color: var(--green-dark); }
        .hint { margin: -3px 0 9px; color: var(--muted); font-size: 13px; }
        input[type="text"], input[type="number"], input[type="date"], input[type="datetime-local"], textarea, select {
            width: 100%;
            border: 1px solid #cbd7e3;
            border-radius: 6px;
            background: #ffffff;
            color: var(--ink);
            padding: 11px 12px;
            font: inherit;
        }
        textarea { min-height: 120px; resize: vertical; }
        input:focus, textarea:focus, select:focus { outline: 3px solid rgba(23, 114, 69, 0.16); border-color: var(--green-dark); }
        .options { display: grid; gap: 10px; margin-top: 8px; }
        .option { display: flex; align-items: center; gap: 10px; color: #344054; font-size: 15px; }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
        }
        .button, button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid var(--green-dark);
            border-radius: 7px;
            background: var(--green-dark);
            color: #ffffff;
            padding: 0 18px;
            font: inherit;
            font-weight: 850;
            text-decoration: none;
            cursor: pointer;
        }
        .button.light, button.light { border-color: #cdd8e4; background: #ffffff; color: #26374a; }
        @media (max-width: 640px) {
            .topbar { padding: 12px 18px; }
            .shell { margin-top: 18px; padding-inline: 12px; }
            .paper { padding: 22px 18px; }
            h1 { font-size: 24px; text-align: left; }
            .meta { text-align: left; }
            .location-banner, .actions { align-items: stretch; flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <img src="{{ asset('images/borderreach-mark.svg') }}" alt="">
            <span>BorderReach</span>
        </div>
        <a href="{{ $backUrl }}">Back</a>
    </header>

    <main class="shell">
        @if(session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form class="paper" method="POST" action="{{ route('collect.forms.store', $form) }}">
            @csrf
            <h1>{{ $schema['title'] ?? $form->title }}</h1>
            <p class="meta">
                Browser collection · {{ $form->moduleLabel() }} · Version {{ $version->version }}
            </p>

            <div class="location-banner">
                <span id="location-status">Location is optional. Allow browser location to attach GPS to this report.</span>
                <button type="button" class="light" id="capture-location">Capture location</button>
            </div>

            <input type="hidden" name="device_latitude" id="device_latitude" value="{{ old('device_latitude') }}">
            <input type="hidden" name="device_longitude" id="device_longitude" value="{{ old('device_longitude') }}">
            <input type="hidden" name="device_location_accuracy_meters" id="device_location_accuracy_meters" value="{{ old('device_location_accuracy_meters') }}">

            @foreach($fields as $field)
                @php
                    $id = $field['id'] ?? null;
                    $type = $field['type'] ?? 'text';
                    $label = $field['label'] ?? $id;
                    $required = (bool) ($field['required'] ?? false);
                    $hint = $field['hint'] ?? null;
                    $oldValue = $id ? old('answers.'.$id) : null;
                @endphp
                @continue(! $id || $type === 'calculate')

                @if($type === 'note')
                    <div class="field">
                        <div class="legend">{{ $label }}</div>
                        @if($hint)<p class="hint">{{ $hint }}</p>@endif
                    </div>
                    @continue
                @endif

                <div class="field">
                    @if(in_array($type, ['select_one', 'select_multiple'], true))
                        <div class="legend">
                            @if($required)<span class="required">*</span>@endif
                            {{ $label }}
                        </div>
                        @if($hint)<p class="hint">{{ $hint }}</p>@endif
                        <div class="options">
                            @foreach($choiceOptions($field) as $option)
                                @php
                                    $value = $option['value'] ?? '';
                                    $optionLabel = $option['label'] ?? $value;
                                    $oldChoices = is_array($oldValue) ? $oldValue : [$oldValue];
                                @endphp
                                <label class="option">
                                    <input
                                        type="{{ $type === 'select_multiple' ? 'checkbox' : 'radio' }}"
                                        name="answers[{{ $id }}]{{ $type === 'select_multiple' ? '[]' : '' }}"
                                        value="{{ $value }}"
                                        @checked(in_array($value, $oldChoices, true))
                                        @required($required && $type === 'select_one')
                                    >
                                    <span>{{ $optionLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <label for="field-{{ $id }}">
                            @if($required)<span class="required">*</span>@endif
                            {{ $label }}
                        </label>
                        @if($hint)<p class="hint">{{ $hint }}</p>@endif

                        @if($type === 'textarea')
                            <textarea id="field-{{ $id }}" name="answers[{{ $id }}]" @required($required)>{{ $oldValue }}</textarea>
                        @else
                            <input
                                id="field-{{ $id }}"
                                type="{{ $type === 'integer' || $type === 'decimal' ? 'number' : ($type === 'date' ? 'date' : ($type === 'datetime' ? 'datetime-local' : 'text')) }}"
                                name="answers[{{ $id }}]"
                                value="{{ $oldValue }}"
                                @if($type === 'decimal') step="any" @endif
                                @required($required)
                            >
                        @endif
                    @endif
                </div>
            @endforeach

            <div class="actions">
                <a class="button light" href="{{ $backUrl }}">Cancel</a>
                <button type="submit">Submit report</button>
            </div>
        </form>
    </main>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        const statusEl = document.getElementById('location-status');
        const captureButton = document.getElementById('capture-location');

        function captureLocation() {
            if (!navigator.geolocation) {
                statusEl.textContent = 'This browser does not support GPS capture. You can still submit the report.';
                return;
            }

            statusEl.textContent = 'Requesting browser location...';
            navigator.geolocation.getCurrentPosition((position) => {
                document.getElementById('device_latitude').value = position.coords.latitude;
                document.getElementById('device_longitude').value = position.coords.longitude;
                document.getElementById('device_location_accuracy_meters').value = position.coords.accuracy;
                statusEl.textContent = `Location captured within ${Math.round(position.coords.accuracy)} meters.`;
            }, () => {
                statusEl.textContent = 'Location was not shared. The report can still be submitted without GPS.';
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        }

        captureButton.addEventListener('click', captureLocation);
    </script>
</body>
</html>
