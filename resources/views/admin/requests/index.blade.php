@extends('admin.layout')

@section('title', 'Service Requests')

@section('content')
<h1 class="page-title">Service Requests</h1>

<form method="GET" action="{{ route('admin.requests') }}" class="filter-bar">
    <div>
        <label>Status</label>
        <select name="status">
            <option value="">All Statuses</option>
            @foreach(['open','matched','confirmed','active','completed','cancelled'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="{{ route('admin.requests') }}" class="btn btn-outline btn-sm">Clear</a>
</form>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Client</th>
                <th>Services</th>
                <th>Type</th>
                <th>Location</th>
                <th>Start</th>
                <th>Pay Rate</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($requests as $req)
            <tr>
                <td style="color: #94a3b8; font-size: 12px;">#{{ $req->id }}</td>
                <td style="font-weight: 600;">{{ $req->client->name ?? '—' }}</td>
                <td>{{ $req->servicesSummary() }}</td>
                <td style="text-transform: capitalize;">{{ $req->applicant_type }}</td>
                <td style="color: #64748b;">{{ $req->city }}, {{ $req->postcode }}</td>
                <td style="color: #64748b; font-size: 12px;">{{ \Carbon\Carbon::parse($req->start_date)->format('d M Y') }}</td>
                <td>
                    @if($req->pay_rate)
                        <span style="color: #166534; font-weight: 600;">{{ $req->pay_rate }}</span>
                    @else
                        <span style="color: #dc2626; font-size: 12px;">Not set</span>
                    @endif
                </td>
                <td>
                    @php
                        $statusColors = [
                            'open' => 'badge-info', 'matched' => 'badge-warning', 'confirmed' => 'badge-warning',
                            'active' => 'badge-success', 'completed' => 'badge-muted', 'cancelled' => 'badge-danger',
                        ];
                    @endphp
                    <span class="badge {{ $statusColors[$req->status] ?? 'badge-muted' }}">{{ ucfirst($req->status) }}</span>
                </td>
                <td>
                    <a href="{{ route('admin.requests.show', $req) }}" class="btn btn-outline btn-sm">Manage</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="text-align: center; color: #94a3b8; padding: 40px;">No service requests found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $requests->links() }}
@endsection
