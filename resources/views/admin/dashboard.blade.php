@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
<h1 class="page-title">Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        <div class="stat-value">{{ $stats['clients'] }}</div>
        <div class="stat-label">Clients</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;"><i class="bi bi-person-badge-fill"></i></div>
        <div class="stat-value">{{ $stats['applicants'] }}</div>
        <div class="stat-label">Applicants</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fefce8;color:#ca8a04;"><i class="bi bi-clipboard-check-fill"></i></div>
        <div class="stat-value">{{ $stats['open_requests'] }}</div>
        <div class="stat-label">Open Requests</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fff7ed;color:#ea580c;"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="stat-value">{{ $stats['pending_rtw'] }}</div>
        <div class="stat-label">Pending Right to Work</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef2f2;color:#dc2626;"><i class="bi bi-shield-exclamation"></i></div>
        <div class="stat-value">{{ $stats['pending_dbs'] }}</div>
        <div class="stat-label">Pending DBS</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-currency-pound"></i></div>
        <div class="stat-value">{{ $stats['requests_without_pay'] }}</div>
        <div class="stat-label">Requests Missing Pay</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="card-header">Quick Actions</div>
        <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
            <a href="{{ route('admin.applicants', ['rtw' => 'pending']) }}" class="btn btn-outline">
                Review Pending Right to Work ({{ $stats['pending_rtw'] }})
            </a>
            <a href="{{ route('admin.requests', ['status' => 'open']) }}" class="btn btn-outline">
                Set Pay Rates on Open Requests ({{ $stats['requests_without_pay'] }})
            </a>
            <a href="{{ route('admin.applicants') }}" class="btn btn-outline">
                View All Applicants
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">About This Panel</div>
        <div class="card-body" style="color: #475569; line-height: 1.7; font-size: 13px;">
            <p><strong>Right to Work</strong> — Set to <em>Verified</em> after checking the applicant's document upload.</p>
            <br>
            <p><strong>DBS Check</strong> — Update when you receive results from the Disclosure and Barring Service.</p>
            <br>
            <p><strong>Pay Rates</strong> — OurHouseHelp sets the pay rate on each service request. Clients do not set pay.</p>
        </div>
    </div>
</div>
@endsection
