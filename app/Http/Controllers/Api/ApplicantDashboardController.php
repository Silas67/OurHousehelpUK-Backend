<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class ApplicantDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $specialties = $user->specialties ?? [];

        $completedJobs = JobApplication::where('applicant_id', $user->id)
            ->where('status', 'accepted')
            ->whereHas('serviceRequest', fn($q) => $q->where('status', 'completed'))
            ->count();

        // Jobs this staff has accepted but client hasn't selected anyone yet
        $pendingOffers = JobApplication::where('applicant_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $verification = [
            ['key' => 'profile_complete', 'label' => 'Profile Complete',     'verified' => $user->hasCompleteProfile()],
            ['key' => 'right_to_work',    'label' => 'Right to Work',        'verified' => $user->right_to_work_status === 'verified'],
            ['key' => 'dbs_check',        'label' => 'DBS Check',            'verified' => $user->dbs_check_status === 'clear'],
            ['key' => 'id_document',      'label' => 'ID Document Uploaded', 'verified' => (bool) $user->id_document_path],
        ];

        // Jobs already responded to — excluded from new matches
        $respondedIds = JobApplication::where('applicant_id', $user->id)
            ->pluck('service_request_id')
            ->toArray();

        $jobMatchesQuery = ServiceRequest::where('status', 'open')
            ->whereNotIn('id', $respondedIds);

        // Only filter by applicant_type if the staff member has one set —
        // it's never collected at registration today, so most staff have
        // it as null and would otherwise never match anything.
        if (!empty($user->applicant_type)) {
            $jobMatchesQuery->where('applicant_type', $user->applicant_type);
        }

        // Only filter by specialties if the staff member has some.
        // Normalize stored values ("Pet Care" → "pet_care") to match service_types enum.
        if (!empty($specialties)) {
            $normalized = array_map(
                fn($s) => strtolower(str_replace(' ', '_', $s)),
                $specialties
            );
            $jobMatchesQuery->where(function ($q) use ($normalized) {
                foreach ($normalized as $specialty) {
                    $q->orWhereJsonContains('service_types', $specialty);
                }
            });
        }

        $jobMatches = $jobMatchesQuery
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($j) => [
                'id'       => $j->id,
                'service'  => $j->servicesSummary(),
                'area'     => $j->city . ', ' . $j->postcode,
                'schedule' => $j->package_name ?? 'Flexible',
                'pay'      => $j->pay_rate,
            ]);

        // Jobs this staff has accepted (pending = waiting for client; accepted = confirmed; rejected = not selected)
        $myApplications = JobApplication::where('applicant_id', $user->id)
            ->whereIn('status', ['pending', 'accepted', 'rejected'])
            ->with('serviceRequest:id,service_types,package_name,pay_rate,status,city,postcode,start_date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'status'     => $a->status,
                'service'    => $a->serviceRequest->servicesSummary(),
                'area'       => $a->serviceRequest->city . ', ' . $a->serviceRequest->postcode,
                'pay'        => $a->serviceRequest->pay_rate,
                'start_date' => $a->serviceRequest->start_date?->toDateString(),
                'job_status' => $a->serviceRequest->status,
            ]);

        // Optional profile extras that enrich the client-facing profile but
        // aren't required — drives a subtle "stand out" nudge on the dashboard.
        $profileExtrasComplete = !empty($user->availability_days)
            && !empty($user->household_types)
            && !empty($user->languages);

        return response()->json([
            'stats'           => ['jobs_completed' => $completedJobs, 'rating' => null, 'pending_offers' => $pendingOffers],
            'is_available'    => (bool) $user->is_available,
            'verification'    => $verification,
            'profile_extras_complete' => $profileExtrasComplete,
            'invitations'     => $user->isVettedForPlacement() ? JobController::invitationsFor($user->id) : [],
            'job_matches'     => ($user->is_available && $user->isVettedForPlacement()) ? $jobMatches : [],
            'my_applications' => $myApplications,
        ]);
    }
}
