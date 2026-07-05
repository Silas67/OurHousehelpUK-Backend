@extends('admin.layout')

@section('title', 'Request #' . $request->id)

@section('content')
<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
    <a href="{{ route('admin.requests') }}" class="btn btn-outline btn-sm">← Back</a>
    <h1 class="page-title" style="margin-bottom: 0;">
        Request #{{ $request->id }} — {{ $request->servicesSummary() }}
    </h1>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

    {{-- Request details --}}
    <div class="card">
        <div class="card-header">Booking Details</div>
        <div class="card-body">
            <div class="detail-row">
                <div class="detail-label">Services</div>
                <div class="detail-value">
                    @foreach($request->service_types as $s)
                        <span class="badge badge-info" style="margin-right: 4px;">{{ ucfirst(str_replace('_',' ',$s)) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Position Type</div>
                <div class="detail-value" style="text-transform: capitalize;">{{ $request->applicant_type }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Address</div>
                <div class="detail-value">
                    {{ $request->address_line_1 }}
                    @if($request->address_line_2), {{ $request->address_line_2 }}@endif<br>
                    {{ $request->city }}, {{ $request->postcode }}
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Start Date</div>
                <div class="detail-value">{{ \Carbon\Carbon::parse($request->start_date)->format('d M Y') }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Duration</div>
                <div class="detail-value">{{ $request->duration_weeks }} week(s)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Package</div>
                <div class="detail-value">{{ $request->package_name ?? '—' }} ({{ $request->days_per_week ?? '?' }} days/week)</div>
            </div>
            @if($request->service_days)
            <div class="detail-row">
                <div class="detail-label">Preferred Days</div>
                <div class="detail-value">{{ $request->service_days }}</div>
            </div>
            @endif
            @if($request->working_hour_start)
            <div class="detail-row">
                <div class="detail-label">Working Hours</div>
                <div class="detail-value">{{ $request->working_hour_start }} – {{ $request->working_hour_end }}</div>
            </div>
            @endif
            <div class="detail-row">
                <div class="detail-label">Client</div>
                <div class="detail-value">{{ $request->client->name ?? '—' }} ({{ $request->client->email ?? '' }})</div>
            </div>
            @if($request->applicant)
            <div class="detail-row">
                <div class="detail-label">Matched Staff</div>
                <div class="detail-value">{{ $request->applicant->name }}</div>
            </div>
            @endif
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px;">

        {{-- Set Pay Rate --}}
        <div class="card">
            <div class="card-header">Pay Rate</div>
            <div class="card-body">
                @if($request->pay_rate)
                    <p style="font-size: 18px; font-weight: 700; color: #166534; margin-bottom: 12px;">{{ $request->pay_rate }}</p>
                @else
                    <p style="color: #dc2626; font-size: 13px; margin-bottom: 12px;">Pay rate not set yet. Applicants will see "TBD" until you set it.</p>
                @endif
                <form method="POST" action="{{ route('admin.requests.pay-rate', $request) }}">
                    @csrf
                    <div class="form-group">
                        <label>Set Pay Rate</label>
                        <input type="text" name="pay_rate" value="{{ $request->pay_rate ?? '' }}" placeholder="e.g. £12/hour or £480/week">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Pay Rate</button>
                </form>
            </div>
        </div>

        {{-- Status control --}}
        <div class="card">
            <div class="card-header">Update Status</div>
            <div class="card-body">
                @php
                    $statusColors = [
                        'open' => 'badge-info', 'matched' => 'badge-warning', 'confirmed' => 'badge-warning',
                        'active' => 'badge-success', 'completed' => 'badge-muted', 'cancelled' => 'badge-danger',
                    ];
                @endphp
                <p style="margin-bottom: 12px;">
                    Current: <span class="badge {{ $statusColors[$request->status] ?? 'badge-muted' }}">{{ ucfirst($request->status) }}</span>
                </p>
                <form method="POST" action="{{ route('admin.requests.status', $request) }}">
                    @csrf
                    <div class="form-group">
                        <label>New Status</label>
                        <select name="status">
                            @foreach(['open','matched','confirmed','active','completed','cancelled'] as $s)
                                <option value="{{ $s }}" {{ $request->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </div>
        </div>

        {{-- Applications --}}
        @if($request->applications->count() > 0)
        <div class="card">
            <div class="card-header">Applications ({{ $request->applications->count() }})</div>
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Status</th>
                        <th>Applied</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($request->applications as $app)
                    <tr>
                        <td>
                            <a href="{{ route('admin.applicants.show', $app->applicant) }}" style="color: #1E3A5F; font-weight: 600; text-decoration: none;">
                                {{ $app->applicant->name ?? '—' }}
                            </a>
                        </td>
                        <td>
                            <span class="badge {{ $app->status === 'accepted' ? 'badge-success' : ($app->status === 'rejected' ? 'badge-danger' : 'badge-warning') }}">
                                {{ ucfirst($app->status) }}
                            </span>
                        </td>
                        <td style="color: #64748b; font-size: 12px;">{{ $app->created_at->format('d M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</div>
@endsection
