@extends('admin.layout')

@section('title', 'Applicants')

@section('content')
<h1 class="page-title">Applicants</h1>

<form method="GET" action="{{ route('admin.applicants') }}" class="filter-bar">
    <div>
        <label>Search</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or email…">
    </div>
    <div>
        <label>Right to Work</label>
        <select name="rtw">
            <option value="">All</option>
            <option value="not_started" {{ request('rtw') === 'not_started' ? 'selected' : '' }}>Not Started</option>
            <option value="pending"  {{ request('rtw') === 'pending'  ? 'selected' : '' }}>Pending</option>
            <option value="verified" {{ request('rtw') === 'verified' ? 'selected' : '' }}>Verified</option>
            <option value="rejected" {{ request('rtw') === 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="{{ route('admin.applicants') }}" class="btn btn-outline btn-sm">Clear</a>
</form>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Type</th>
                <th>Right to Work</th>
                <th>DBS</th>
                <th>Profile</th>
                <th>Joined</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($applicants as $applicant)
            <tr>
                <td style="font-weight: 600;">{{ $applicant->name }} {{ $applicant->last_name }}</td>
                <td style="color: #64748b;">{{ $applicant->email }}</td>
                <td><span class="badge badge-info" style="text-transform: capitalize;">{{ $applicant->applicant_type ?? '—' }}</span></td>
                <td>
                    @php $rtw = $applicant->right_to_work_status ?? 'not_started'; @endphp
                    <span class="badge {{ $rtw === 'verified' ? 'badge-success' : ($rtw === 'rejected' ? 'badge-danger' : 'badge-warning') }}">
                        {{ ucfirst(str_replace('_', ' ', $rtw)) }}
                    </span>
                </td>
                <td>
                    @php $dbs = $applicant->dbs_check_status ?? 'not_started'; @endphp
                    <span class="badge {{ $dbs === 'clear' ? 'badge-success' : ($dbs === 'flagged' ? 'badge-danger' : 'badge-warning') }}">
                        {{ ucfirst(str_replace('_', ' ', $dbs)) }}
                    </span>
                </td>
                <td>
                    @if($applicant->hasCompleteProfile())
                        <span class="badge badge-success">Complete</span>
                    @else
                        <span class="badge badge-muted">Incomplete</span>
                    @endif
                </td>
                <td style="color: #64748b; font-size: 12px;">{{ $applicant->created_at->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('admin.applicants.show', $applicant) }}" class="btn btn-outline btn-sm">Review</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">No applicants found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $applicants->links() }}
@endsection
