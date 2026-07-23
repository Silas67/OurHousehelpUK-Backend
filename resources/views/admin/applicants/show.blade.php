@extends('admin.layout')

@section('title', $user->name . ' — Applicant')

@section('content')
<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
    <a href="{{ route('admin.applicants') }}" class="btn btn-outline btn-sm">← Back</a>
    <h1 class="page-title" style="margin-bottom: 0;">{{ $user->name }} {{ $user->last_name }}</h1>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

    {{-- Profile details --}}
    <div class="card">
        <div class="card-header">Personal Information</div>
        <div class="card-body">
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value">{{ $user->email }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Phone</div>
                <div class="detail-value">{{ $user->phone ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Date of Birth</div>
                <div class="detail-value">{{ $user->dob?->format('d M Y') ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Gender</div>
                <div class="detail-value" style="text-transform: capitalize;">{{ $user->gender ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">National Insurance No.</div>
                <div class="detail-value" style="font-family: monospace; letter-spacing: 0.5px;">{{ $user->ni_number ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Applicant Type</div>
                <div class="detail-value" style="text-transform: capitalize;">{{ $user->applicant_type ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Address</div>
                <div class="detail-value">
                    {{ $user->address_line_1 }}<br>
                    @if($user->address_line_2){{ $user->address_line_2 }}<br>@endif
                    {{ $user->city }}{{ $user->county ? ', ' . $user->county : '' }}<br>
                    {{ $user->postcode }}
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Specialties</div>
                <div class="detail-value">
                    @foreach($user->specialties ?? [] as $s)
                        <span class="badge badge-info" style="margin-right: 4px; margin-bottom: 4px;">{{ $s }}</span>
                    @endforeach
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Bio</div>
                <div class="detail-value" style="color: #475569; line-height: 1.5;">{{ $user->bio ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Experience</div>
                <div class="detail-value">{{ $user->years_of_experience ?? '—' }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Right to Work Doc</div>
                <div class="detail-value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $user->right_to_work_document_type ?? '—') }}</div>
            </div>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px;">

        {{-- Document uploads --}}
        <div class="card">
            <div class="card-header">Uploaded Documents</div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 12px;">
                @if($user->profile_photo_path)
                    <div>
                        <div class="detail-label" style="margin-bottom: 6px;">Profile Photo</div>
                        <img src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="Profile Photo"
                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;">
                    </div>
                @endif
                @if($user->id_document_path)
                    <div>
                        <div class="detail-label" style="margin-bottom: 6px;">ID Document ({{ str_replace('_', ' ', $user->right_to_work_document_type ?? '') }})</div>
                        <a href="{{ route('admin.applicants.document', [$user, 'id_document']) }}" target="_blank" class="btn btn-outline btn-sm">View Document</a>
                    </div>
                @endif
                @if($user->cv_path)
                    <div>
                        <div class="detail-label" style="margin-bottom: 6px;">CV</div>
                        <a href="{{ route('admin.applicants.document', [$user, 'cv']) }}" target="_blank" class="btn btn-outline btn-sm">View CV</a>
                    </div>
                @endif
                @if($user->dbs_certificate_path)
                    <div>
                        <div class="detail-label" style="margin-bottom: 6px;">DBS Certificate</div>
                        <a href="{{ route('admin.applicants.document', [$user, 'dbs_certificate']) }}" target="_blank" class="btn btn-outline btn-sm">View Certificate</a>
                    </div>
                @endif
                @if(!$user->profile_photo_path && !$user->id_document_path && !$user->cv_path && !$user->dbs_certificate_path)
                    <p style="color: #94a3b8; font-size: 13px;">No documents uploaded.</p>
                @endif
            </div>
        </div>

        {{-- Verification panel --}}
        <div class="card">
            <div class="card-header">Update Verification Status</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.applicants.verify', $user) }}">
                    @csrf
                    <div class="form-group">
                        <label>Right to Work Status</label>
                        <select name="right_to_work_status">
                            <option value="not_started" {{ ($user->right_to_work_status ?? 'not_started') === 'not_started' ? 'selected' : '' }}>Not Started</option>
                            <option value="pending"  {{ ($user->right_to_work_status ?? '') === 'pending'  ? 'selected' : '' }}>Pending</option>
                            <option value="verified" {{ ($user->right_to_work_status ?? '') === 'verified' ? 'selected' : '' }}>Verified ✓</option>
                            <option value="rejected" {{ ($user->right_to_work_status ?? '') === 'rejected' ? 'selected' : '' }}>Rejected ✗</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>DBS Check Status</label>
                        <select name="dbs_check_status">
                            <option value="not_started" {{ ($user->dbs_check_status ?? 'not_started') === 'not_started' ? 'selected' : '' }}>Not Started</option>
                            <option value="pending"   {{ ($user->dbs_check_status ?? '') === 'pending'   ? 'selected' : '' }}>Pending</option>
                            <option value="clear"     {{ ($user->dbs_check_status ?? '') === 'clear'     ? 'selected' : '' }}>Clear ✓</option>
                            <option value="flagged"   {{ ($user->dbs_check_status ?? '') === 'flagged'   ? 'selected' : '' }}>Flagged ✗</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>DBS Certificate No. (optional)</label>
                            <input type="text" name="dbs_certificate_number" value="{{ $user->dbs_certificate_number ?? '' }}" placeholder="e.g. 001234567890">
                        </div>
                        <div class="form-group">
                            <label>DBS Check Date (optional)</label>
                            <input type="date" name="dbs_check_date" value="{{ $user->dbs_check_date?->format('Y-m-d') ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>References Checked (count)</label>
                        <input type="number" name="references_checked" min="0" max="20" value="{{ $user->references_checked ?? '' }}" placeholder="e.g. 2">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 4px;">Save Changes</button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
