<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\ServiceRequest;
use App\Notifications\JobAcceptedNotification;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $specialties = $user->specialties ?? [];

        // Right to work and DBS must both be cleared before a worker can
        // see/accept jobs.
        if (!$user->isVettedForPlacement()) {
            return response()->json(['jobs' => []]);
        }

        // Exclude jobs already responded to (accepted or declined)
        $respondedIds = JobApplication::where('applicant_id', $user->id)
            ->pluck('service_request_id')
            ->toArray();

        $query = ServiceRequest::where('status', 'open')
            ->whereNotIn('id', $respondedIds);

        // Only filter by applicant_type if the staff member has one set
        if (!empty($user->applicant_type)) {
            $query->where('applicant_type', $user->applicant_type);
        }

        // Only filter by specialties if the staff member has some.
        // Normalize stored values ("Pet Care" → "pet_care") to match service_types enum.
        if (!empty($specialties)) {
            $normalized = array_map(
                fn($s) => strtolower(str_replace(' ', '_', $s)),
                $specialties
            );
            $query->where(function ($q) use ($normalized) {
                foreach ($normalized as $specialty) {
                    $q->orWhereJsonContains('service_types', $specialty);
                }
            });
        }

        $jobs = $query->orderByDesc('created_at')
            ->get()
            ->map(fn($j) => [
                'id'             => $j->id,
                'services'       => $j->service_types,
                'service'        => $j->servicesSummary(),
                'area'           => $j->city . ', ' . $j->postcode,
                'schedule'       => $j->package_name ?? ($j->service_days ?? 'Flexible'),
                'pay'            => $j->pay_rate,
                'applicant_type' => $j->applicant_type,
                'start_date'     => $j->start_date?->toDateString(),
            ]);

        return response()->json(['jobs' => $jobs]);
    }

    public function show(Request $request, ServiceRequest $job)
    {
        if ($job->status !== 'open') {
            return response()->json(['message' => 'This job is no longer available.'], 404);
        }

        $user = $request->user();
        $application = $job->applications()->where('applicant_id', $user->id)->first();

        return response()->json([
            'job' => [
                'id'             => $job->id,
                'services'       => $job->service_types,
                'service'        => $job->servicesSummary(),
                'area'           => $job->city . ', ' . $job->postcode,
                'address'        => $job->address_line_1 . ($job->address_line_2 ? ', ' . $job->address_line_2 : ''),
                'schedule'       => $job->package_name ?? 'Flexible',
                'service_days'   => $job->service_days,
                'working_hours'  => $job->working_hour_start && $job->working_hour_end
                    ? $job->working_hour_start . ' – ' . $job->working_hour_end
                    : null,
                'pay'            => $job->pay_rate,
                'applicant_type' => $job->applicant_type,
                'duration'       => $job->duration_weeks === 1 ? 'One-off' : $job->duration_weeks . ' weeks',
                'start_date'     => $job->start_date?->toDateString(),
                'end_date'       => $job->end_date?->toDateString(),
                'accepted'       => $application?->status === 'pending',
                'declined'       => $application?->status === 'declined',
                'applied'        => $application !== null,
            ],
        ]);
    }

    public function accept(Request $request, ServiceRequest $job)
    {
        if ($job->status !== 'open') {
            return response()->json(['message' => 'This job is no longer available.'], 422);
        }

        $user = $request->user();

        if (!$user->isVettedForPlacement()) {
            return response()->json(['message' => 'Your right to work and DBS check must be cleared before you can accept jobs.'], 422);
        }

        $already = JobApplication::where('service_request_id', $job->id)
            ->where('applicant_id', $user->id)
            ->exists();

        if ($already) {
            return response()->json(['message' => 'You have already responded to this job.'], 422);
        }

        JobApplication::create([
            'service_request_id' => $job->id,
            'applicant_id'       => $user->id,
            'status'             => 'pending',
        ]);

        // Notify the client
        $job->client->notify(new JobAcceptedNotification($job, $user));

        return response()->json(['message' => 'You have accepted this job. The client will be notified.'], 201);
    }

    public function decline(Request $request, ServiceRequest $job)
    {
        $user = $request->user();

        $already = JobApplication::where('service_request_id', $job->id)
            ->where('applicant_id', $user->id)
            ->exists();

        if ($already) {
            return response()->json(['message' => 'You have already responded to this job.'], 422);
        }

        JobApplication::create([
            'service_request_id' => $job->id,
            'applicant_id'       => $user->id,
            'status'             => 'declined',
        ]);

        return response()->json(['message' => 'Job removed from your list.'], 201);
    }

    /**
     * Open jobs a client has personally invited this staff member to.
     * Shared by the applicant dashboard so the home screen needs one fetch.
     */
    public static function invitationsFor(int $applicantId): array
    {
        return JobApplication::where('applicant_id', $applicantId)
            ->where('status', 'invited')
            ->with('serviceRequest:id,service_types,package_name,service_days,pay_rate,status,city,postcode,start_date,applicant_type')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn($a) => $a->serviceRequest && $a->serviceRequest->status === 'open')
            ->map(fn($a) => [
                'id'             => $a->id,
                'booking_id'     => $a->service_request_id,
                'service'        => $a->serviceRequest->servicesSummary(),
                'applicant_type' => $a->serviceRequest->applicant_type,
                'area'           => $a->serviceRequest->city . ', ' . $a->serviceRequest->postcode,
                'schedule'       => $a->serviceRequest->package_name ?? ($a->serviceRequest->service_days ?? 'Flexible'),
                'pay'            => $a->serviceRequest->pay_rate,
                'start_date'     => $a->serviceRequest->start_date?->toDateString(),
            ])
            ->values()
            ->all();
    }

    public function acceptInvite(Request $request, ServiceRequest $job)
    {
        $user = $request->user();

        if (!$user->isVettedForPlacement()) {
            return response()->json(['message' => 'Your right to work and DBS check must be cleared before you can accept jobs.'], 422);
        }

        if ($job->status !== 'open') {
            return response()->json(['message' => 'This job is no longer available.'], 422);
        }

        $application = JobApplication::where('service_request_id', $job->id)
            ->where('applicant_id', $user->id)
            ->where('status', 'invited')
            ->first();

        if (!$application) {
            return response()->json(['message' => 'Invitation not found.'], 404);
        }

        // Accepting an invite puts the staff member into the client's pool of
        // interested applicants — the client still confirms and pays to select.
        $application->update(['status' => 'pending']);

        $job->client->notify(new JobAcceptedNotification($job, $user));

        return response()->json(['message' => 'Invitation accepted. The client will be notified.']);
    }

    public function declineInvite(Request $request, ServiceRequest $job)
    {
        $application = JobApplication::where('service_request_id', $job->id)
            ->where('applicant_id', $request->user()->id)
            ->where('status', 'invited')
            ->first();

        if (!$application) {
            return response()->json(['message' => 'Invitation not found.'], 404);
        }

        $application->update(['status' => 'declined']);

        return response()->json(['message' => 'Invitation declined.']);
    }

    public function confirmedJob(Request $request, ServiceRequest $booking)
    {
        $user = $request->user();

        // Only the confirmed applicant can see client contact details
        if ((int) $booking->applicant_id !== $user->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $client = $booking->client;

        return response()->json([
            'booking' => [
                'id'             => $booking->id,
                'service'        => $booking->servicesSummary(),
                'start_date'     => $booking->start_date?->toDateString(),
                'end_date'       => $booking->end_date?->toDateString(),
                'schedule'       => $booking->package_name ?? ($booking->service_days ?? 'Flexible'),
                'working_hours'  => $booking->working_hour_start && $booking->working_hour_end
                    ? $booking->working_hour_start . ' – ' . $booking->working_hour_end
                    : null,
                'pay'            => $booking->pay_rate,
                'duration'       => $booking->duration_weeks === 1 ? 'One-off' : $booking->duration_weeks . ' weeks',
                'status'         => $booking->status,
                'client' => [
                    'name'    => $client->name,
                    'phone'   => $client->phone,
                    'email'   => $client->email,
                    'address' => trim(implode(', ', array_filter([
                        $booking->address_line_1,
                        $booking->address_line_2,
                        $booking->city,
                        $booking->postcode,
                    ]))),
                ],
            ],
        ]);
    }

    public function myApplications(Request $request)
    {
        $apps = JobApplication::where('applicant_id', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted', 'rejected'])
            ->with('serviceRequest:id,service_types,package_name,service_days,pay_rate,status,city,postcode,start_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'booking_id'     => $a->service_request_id,
                'status'         => $a->status,
                'service'        => $a->serviceRequest->servicesSummary(),
                'applicant_type' => $a->serviceRequest->applicant_type,
                'area'           => $a->serviceRequest->city . ', ' . $a->serviceRequest->postcode,
                'schedule'       => $a->serviceRequest->package_name ?? 'Flexible',
                'pay'            => $a->serviceRequest->pay_rate,
                'start_date'     => $a->serviceRequest->start_date?->toDateString(),
                'job_status'     => $a->serviceRequest->status,
            ]);

        return response()->json(['applications' => $apps]);
    }
}
