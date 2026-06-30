@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Deployment Requests</h1>
            <p class="subtitle">Review country and agency requests from the public deployment form.</p>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Country / Agency</th>
                <th>Contact</th>
                <th>Plan</th>
                <th>Scope</th>
                <th>Modules</th>
                <th>Status</th>
                <th>Received</th>
            </tr>
            </thead>
            <tbody>
            @forelse($deploymentRequests as $requestRecord)
                <tr>
                    <td>
                        <strong>{{ $requestRecord->country_name }}</strong>
                        <div class="field-help">{{ $requestRecord->agency_name }}</div>
                    </td>
                    <td>
                        {{ $requestRecord->contact_name }}
                        <div class="mono">{{ $requestRecord->contact_email }}{{ $requestRecord->contact_phone ? "\n".$requestRecord->contact_phone : '' }}</div>
                        @if($requestRecord->contact_role)
                            <div class="field-help">{{ $requestRecord->contact_role }}</div>
                        @endif
                    </td>
                    <td>
                        {{ \App\Models\Country::deploymentPlanLabels()[$requestRecord->deployment_plan] ?? $requestRecord->deployment_plan }}
                        <div class="field-help">{{ \App\Models\Country::deploymentTypeLabels()[$requestRecord->deployment_type] ?? $requestRecord->deployment_type }}</div>
                    </td>
                    <td>
                        <div>{{ $requestRecord->expected_posts ?: '-' }} posts</div>
                        <div>{{ $requestRecord->expected_users ?: '-' }} users</div>
                    </td>
                    <td>{{ implode(', ', $requestRecord->modules ?? []) ?: '-' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.deployment-requests.update', $requestRecord) }}">
                            @csrf
                            <select name="status" onchange="this.form.submit()">
                                @foreach($statusLabels as $status => $label)
                                    <option value="{{ $status }}" @selected($requestRecord->status === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td>{{ $requestRecord->created_at }}</td>
                </tr>
                @if($requestRecord->message)
                    <tr>
                        <td colspan="7">
                            <label>Message</label>
                            <div>{{ $requestRecord->message }}</div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="7">No deployment requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $deploymentRequests->links() }}
@endsection
