@extends('admin.layout')

@section('title', 'Packages')

@section('content')
<h1 class="page-title">Packages &amp; Pricing</h1>

<p style="color: #64748b; font-size: 13px; margin-bottom: 24px; max-width: 640px;">
    Set the monthly cost for each package. When a client books and selects a package, the pay rate is
    automatically set from this cost (e.g. £480.00 becomes <strong>£480.00/month</strong> on the booking).
    Set cost to <strong>£0.00</strong> to mark a package as "price on request" (pay rate will not be auto-set).
</p>

@foreach($packages as $pkg)
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <span>{{ $pkg->name }}</span>
        <span class="badge {{ $pkg->is_active ? 'badge-success' : 'badge-muted' }}">
            {{ $pkg->is_active ? 'Active' : 'Hidden' }}
        </span>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: auto 1fr; gap: 20px; align-items: start;">
            <div style="min-width: 120px;">
                <div class="detail-label">Days / Week</div>
                <div style="font-size: 28px; font-weight: 800; color: #1E3A5F; line-height: 1;">{{ $pkg->days_per_week }}</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">days/week</div>
            </div>
            <form method="POST" action="{{ route('admin.packages.update', $pkg) }}">
                @csrf
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Monthly Cost (£)</label>
                        <input type="number" name="cost" value="{{ number_format($pkg->cost, 2, '.', '') }}" min="0" step="0.01" placeholder="e.g. 480.00">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Description (optional)</label>
                        <input type="text" name="description" value="{{ $pkg->description ?? '' }}" placeholder="Short description shown to clients">
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; text-transform: none; font-weight: 500; letter-spacing: 0; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ $pkg->is_active ? 'checked' : '' }} style="width: auto;">
                        Show this package to clients
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    @if($pkg->cost > 0)
                        <span style="font-size: 12px; color: #166534; font-weight: 600;">
                            → Bookings will be set to £{{ number_format($pkg->cost, 2) }}/month
                        </span>
                    @else
                        <span style="font-size: 12px; color: #dc2626;">
                            → No cost set — pay rate will not be auto-populated
                        </span>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
