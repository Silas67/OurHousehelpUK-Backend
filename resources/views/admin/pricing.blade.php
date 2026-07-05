@extends('admin.layout')

@section('title', 'Service Pricing')

@section('content')
<h1 class="page-title">Service Pricing Settings</h1>

<p style="color: #64748b; font-size: 13px; margin-bottom: 24px; max-width: 700px;">
    These values feed directly into the cost calculator.
    <strong>PRS (Cleaning)</strong> uses apartment type cost — set below.
    <strong>PMS services</strong> (Cooking, Laundry etc.) use their own monthly base cost.
    <strong>PSS services</strong> (Pet Care) use the flat extra service fee.
</p>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

{{-- Services --}}
<div>
    <div class="card">
        <div class="card-header">Services — Monthly Base Cost (PMS)</div>
        <div class="card-body">
            @foreach($services as $svc)
            <form method="POST" action="{{ route('admin.pricing.service', $svc) }}" style="display: flex; align-items: flex-end; gap: 10px; margin-bottom: 14px;">
                @csrf
                <div style="flex: 1;">
                    <label>{{ $svc->service_name }}
                        <span style="font-size: 10px; font-weight: 400; text-transform: none; letter-spacing: 0; margin-left: 6px;"
                              class="badge {{ $svc->service_type === 'PRS' ? 'badge-info' : ($svc->service_type === 'PSS' ? 'badge-warning' : 'badge-muted') }}">
                            {{ $svc->service_type }}
                        </span>
                    </label>
                    <input type="number" name="base_cost" value="{{ number_format($svc->base_cost, 2, '.', '') }}"
                           min="0" step="0.01" placeholder="£/month"
                           {{ $svc->service_type === 'PRS' ? 'disabled title=Cleaning cost comes from apartment type' : '' }}>
                    @if($svc->service_type === 'PRS')
                        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Cost set by apartment type below</div>
                    @endif
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                    <label style="display: flex; align-items: center; gap: 6px; text-transform: none; font-weight: 500; letter-spacing: 0; white-space: nowrap;">
                        <input type="checkbox" name="is_active" value="1" {{ $svc->is_active ? 'checked' : '' }} style="width: auto;">
                        Active
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm" {{ $svc->service_type === 'PRS' ? 'disabled' : '' }}>Save</button>
                </div>
            </form>
            @endforeach
        </div>
    </div>

    {{-- Flat extra service cost (PSS) --}}
    <div class="card">
        <div class="card-header">Extra Service Cost (PSS flat fee)</div>
        <div class="card-body">
            <p style="font-size: 12px; color: #64748b; margin-bottom: 12px;">
                Applied to PSS-type secondary services (e.g. Pet Care when it's not the main service).
            </p>
            <form method="POST" action="{{ route('admin.pricing.extra') }}" style="display: flex; gap: 10px; align-items: flex-end;">
                @csrf
                <div style="flex: 1;">
                    <label>Monthly Flat Fee (£)</label>
                    <input type="number" name="cost" value="{{ number_format($extraCost->cost, 2, '.', '') }}" min="0" step="0.01">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
        </div>
    </div>
</div>

<div>
    {{-- Apartment types --}}
    <div class="card">
        <div class="card-header">Apartment Types — Cleaning Monthly Cost</div>
        <div class="card-body">
            @foreach($apartmentTypes as $apt)
            <form method="POST" action="{{ route('admin.pricing.apartment', $apt) }}" style="display: flex; align-items: flex-end; gap: 10px; margin-bottom: 14px;">
                @csrf
                <div style="flex: 1;">
                    <label>{{ $apt->name }}</label>
                    <input type="number" name="cost" value="{{ number_format($apt->cost, 2, '.', '') }}" min="0" step="0.01" placeholder="£/month">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </form>
            @endforeach
        </div>
    </div>

    {{-- Management plans --}}
    <div class="card">
        <div class="card-header">Management Plans — Markup %</div>
        <div class="card-body">
            @foreach($mgmtPlans as $plan)
            <form method="POST" action="{{ route('admin.pricing.plan', $plan) }}" style="margin-bottom: 20px;">
                @csrf
                <div class="form-group">
                    <label>{{ $plan->name }} (slug: <code>{{ $plan->slug }}</code>)</label>
                    <div style="display: flex; gap: 10px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label style="text-transform: none; font-weight: 400; letter-spacing: 0;">Platform Markup (%)</label>
                            <input type="number" name="platform_markup" value="{{ number_format($plan->platform_markup, 2, '.', '') }}" min="0" max="100" step="0.5">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </div>
                </div>
                <div class="form-group">
                    <label style="text-transform: none; font-weight: 400; letter-spacing: 0; font-size: 12px;">Description shown to clients</label>
                    <input type="text" name="description" value="{{ $plan->description ?? '' }}" placeholder="Short description">
                </div>
            </form>
            @endforeach
        </div>
    </div>
</div>

</div>
@endsection
