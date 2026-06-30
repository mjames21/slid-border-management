@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Submission #{{ $submission->id }}</h1>
            <p class="subtitle">{{ $submission->form_id }} v{{ $submission->form_version }} captured by {{ $submission->device_id }}</p>
        </div>
        <a class="button light" href="{{ route('admin.submissions.index') }}">Back</a>
    </div>

    <div class="summary-grid">
        <div class="summary-item"><label>Tenant</label><div>{{ $submission->country_code }}</div></div>
        <div class="summary-item"><label>Module</label><div>{{ $submission->reportingModuleLabel() }}</div></div>
        <div class="summary-item"><label>Status</label><div><span class="tag {{ $submission->status }}">{{ ucfirst($submission->status) }}</span></div></div>
        <div class="summary-item"><label>Received</label><div>{{ $submission->received_at }}</div></div>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Submission custody</h2>
                <p class="panel-subtitle">Operational context attached at sync time.</p>
            </div>
        </div>
        <div class="summary-grid" style="padding:18px;margin-bottom:0;">
            <div class="summary-item"><label>Border Post</label><div>{{ $submission->border_post_code ?: '-' }}{{ $submission->region ? ' / '.$submission->region : '' }}</div></div>
            <div class="summary-item"><label>Digital Address</label><div class="mono">{{ $submission->border_post_digital_address ?: '-' }}</div></div>
            <div class="summary-item"><label>Device</label><div class="mono">{{ $submission->device_id }}</div></div>
            <div class="summary-item"><label>Local ID</label><div class="mono">{{ $submission->local_id }}</div></div>
            <div class="summary-item">
                <label>Device Lon/Lat</label>
                <div class="mono">
                    @if($submission->device_longitude !== null && $submission->device_latitude !== null)
                        {{ $submission->device_longitude }}, {{ $submission->device_latitude }}
                    @else
                        Not provided
                    @endif
                </div>
            </div>
            <div class="summary-item"><label>Location Accuracy</label><div>{{ $submission->device_location_accuracy_meters ? $submission->device_location_accuracy_meters.' m' : '-' }}</div></div>
            <div class="summary-item"><label>Location Captured</label><div>{{ $submission->device_location_captured_at ?: '-' }}</div></div>
            <div class="summary-item"><label>Client Updated</label><div>{{ $submission->client_updated_at }}</div></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Report Answers</h2>
                <p class="panel-subtitle">Schema-matched answers are shown in field order. Unknown fields are preserved for review.</p>
            </div>
            <span class="tag">{{ count($answerRows) }} fields</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th style="width: 28%;">Field</th>
                    <th style="width: 24%;">Field ID</th>
                    <th style="width: 16%;">Type</th>
                    <th>Answer</th>
                </tr>
                </thead>
                <tbody>
                @forelse($answerRows as $row)
                    <tr>
                        <td>
                            <div class="record-title">{{ $row['label'] }}</div>
                            @unless($row['from_schema'])
                                <div class="field-help">Not found in form schema</div>
                            @endunless
                        </td>
                        <td><span class="mono inline">{{ $row['field_id'] }}</span></td>
                        <td><span class="tag">{{ $row['type'] }}</span></td>
                        <td>{{ $row['value'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No answers were captured for this submission.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
