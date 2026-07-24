<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseService;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\JobFilledNotification;
use App\Notifications\StaffConfirmedNotification;
use App\Services\BookingCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = ServiceRequest::where('client_id', $request->user()->id)
            ->with('applicant:id,name,profile_photo_path')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($b) => [
                'id'             => $b->id,
                'services'       => $b->service_types,
                'services_label' => $b->servicesSummary(),
                'applicant_type' => $b->applicant_type,
                'package'        => $b->package_name,
                'city'           => $b->city,
                'start_date'     => $b->start_date?->toDateString(),
                'status'         => $b->status,
                'staff_name'     => $b->applicant?->name,
                'role'           => $b->servicesSummary(),
                'date'           => $b->start_date?->toDateString(),
            ]);

        return response()->json(['bookings' => $bookings]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_types'      => ['required', 'array', 'min:1'],
            'service_types.*'    => ['string', 'in:cleaning,deep_cleaning,cooking,childcare,elderly_care,laundry,errands,window_cleaning,pet_care'],
            'applicant_type'     => ['required', 'string', 'in:semi-live-in,live-out'],
            'management_plan'    => ['nullable', 'string', 'in:client-managed,company-managed'],
            'apartment_type_id'  => ['nullable', 'integer', 'exists:apartment_types,id'],
            'bedrooms'           => ['nullable', 'integer', 'min:0', 'max:10'],
            'bathrooms'          => ['nullable', 'integer', 'min:0', 'max:10'],
            'kitchens'           => ['nullable', 'integer', 'min:0', 'max:10'],
            'hours_per_session'  => ['nullable', 'numeric', 'min:1', 'max:12', 'multiple_of:0.5'],
            'address_line_1'     => ['required', 'string', 'max:255'],
            'address_line_2'     => ['nullable', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:255'],
            'postcode'           => ['required', 'string', 'max:10'],
            'start_date'         => ['required', 'date', 'after_or_equal:today'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'duration_weeks'     => ['required', 'integer', 'in:1,2,3,4,8,12'],
            'service_days'       => ['nullable', 'string', 'max:255'],
            'working_hour_start' => ['nullable', 'string', 'max:10'],
            'working_hour_end'   => ['nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Days per week is derived from the days the client selected (packages
        // have been removed). One-off bookings are a single session.
        $daysPerWk = $request->filled('service_days')
            ? count(array_filter(array_map('trim', explode(',', $request->service_days))))
            : 1;
        $daysPerWk = max(1, $daysPerWk);

        // Calculate cost using the service
        $managementPlan = $request->management_plan ?? 'company-managed';
        $costService = new BookingCostService();
        $costResult  = $costService->calculate(
            $request->service_types,
            null,
            $managementPlan,
            $request->duration_weeks,
            $request->apartment_type_id
        );

        // Calculate per-session quoted amount for card charge on completion.
        // A one-off is a single live-out session; semi-live-in with a 1-week
        // duration is still days_per_week sessions, not one.
        $isOneOff   = $request->duration_weeks === 1 && $request->applicant_type !== 'semi-live-in';
        $services   = HouseService::whereIn('slug', $request->service_types)->get();
        $avgRate    = $services->isNotEmpty() ? $services->avg('hourly_rate') : 14.0;
        $sessHours  = $request->applicant_type === 'semi-live-in' ? 10 : ($request->hours_per_session ?? 3);
        $totalSessions = $isOneOff ? 1 : ($daysPerWk * $request->duration_weeks);
        $quotedPence = (int) round($avgRate * $sessHours * $totalSessions * 100);

        // pay_rate is a display-only field (e.g. "£14.00/hr") — cost_breakdown's
        // staff_salary always comes out £0 since every house_services.base_cost
        // is £0 and apartment_type_id is never sent by the app, so use the
        // same real hourly_rate figure quoted_pence is built from instead.
        $payRate = $avgRate > 0 ? ('£' . number_format($avgRate, 2) . '/hr') : null;

        $booking = ServiceRequest::create([
            'client_id'          => $request->user()->id,
            'service_types'      => $request->service_types,
            'applicant_type'     => $request->applicant_type,
            'management_plan'    => $managementPlan,
            'apartment_type_id'  => $request->apartment_type_id,
            'bedrooms'           => $request->bedrooms ?? 0,
            'bathrooms'          => $request->bathrooms ?? 0,
            'kitchens'           => $request->kitchens ?? 1,
            'hours_per_session'  => $request->applicant_type === 'semi-live-in' ? 10 : ($request->hours_per_session ?? 0),
            'feature_cost'       => $costResult['apartment_cost'],
            'address_line_1'     => $request->address_line_1,
            'address_line_2'     => $request->address_line_2,
            'city'               => $request->city,
            'postcode'           => strtoupper(trim($request->postcode)),
            'start_date'         => $request->start_date,
            'end_date'           => $request->end_date,
            'duration_weeks'     => $request->duration_weeks,
            'package_name'       => null,
            'days_per_week'      => $daysPerWk,
            'service_days'       => $request->service_days,
            'working_hour_start' => $request->working_hour_start,
            'working_hour_end'   => $request->working_hour_end,
            'pay_rate'           => $payRate,
            'cost_breakdown'     => $costResult,
            'quoted_pence'       => $quotedPence,
            'status'             => 'open',
        ]);

        return response()->json(['booking' => $booking], 201);
    }

    public function show(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $booking->load([
            'applicant:id,name,profile_photo_path,phone,bio,years_of_experience',
            'applications.applicant:id,name,profile_photo_path,years_of_experience',
        ]);

        $data = $booking->toArray();
        $data['start_date']    = $booking->start_date?->toDateString();
        $data['end_date']      = $booking->end_date?->toDateString();
        $data['title']         = $booking->servicesSummary();
        $data['schedule']      = $booking->package_name ?? ($booking->service_days ?? null);

        return response()->json(['booking' => $data]);
    }

    public function cancel(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (in_array($booking->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Booking cannot be cancelled.'], 422);
        }

        $booking->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Booking cancelled.']);
    }

    public function destroy(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Only a cancelled booking can be removed — active/open/completed
        // bookings stay for records and to protect in-flight work.
        if ($booking->status !== 'cancelled') {
            return response()->json(['message' => 'Only cancelled bookings can be deleted.'], 422);
        }

        // Applications, ratings and payments cascade-delete with the booking.
        $booking->delete();

        return response()->json(['message' => 'Booking deleted.']);
    }

    public function confirm(Request $request, ServiceRequest $booking, $applicantId)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (!in_array($booking->status, ['open', 'matched'])) {
            return response()->json(['message' => 'This booking cannot be confirmed at this stage.'], 422);
        }

        $application = $booking->applications()
            ->where('applicant_id', $applicantId)
            ->where('status', 'pending')
            ->first();

        if (!$application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $application->update(['status' => 'accepted']);
        $booking->applications()->where('applicant_id', '!=', $applicantId)->update(['status' => 'rejected']);
        $booking->update(['status' => 'confirmed', 'applicant_id' => $applicantId]);

        // Notify selected staff
        $selectedStaff = User::find($applicantId);
        if ($selectedStaff) {
            $selectedStaff->notify(new StaffConfirmedNotification($booking));
        }

        // Notify unselected staff who also accepted
        $rejectedStaffIds = $booking->applications()
            ->where('applicant_id', '!=', $applicantId)
            ->pluck('applicant_id');
        $rejectedStaff = User::whereIn('id', $rejectedStaffIds)->get();
        foreach ($rejectedStaff as $staff) {
            $staff->notify(new JobFilledNotification($booking));
        }

        return response()->json(['message' => 'Applicant confirmed.', 'booking' => $booking->fresh()]);
    }

    public function activate(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($booking->status !== 'confirmed') {
            return response()->json(['message' => 'Booking must be confirmed before activating.'], 422);
        }
        $booking->update(['status' => 'active']);
        return response()->json(['message' => 'Booking is now active.', 'booking' => $booking->fresh()]);
    }

    public function complete(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($booking->status !== 'active') {
            return response()->json(['message' => 'Booking must be active before completing.'], 422);
        }

        // Payment is taken upfront at checkout when the client selects a
        // staff member, so completion just closes out the booking — no
        // charge happens here.
        $booking->update(['status' => 'completed']);

        return response()->json(['message' => 'Booking marked as completed.', 'booking' => $booking->fresh()]);
    }

    public function invite(Request $request, ServiceRequest $booking, User $staff)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($staff->account_type !== 'applicant') {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($booking->applications()->where('applicant_id', $staff->id)->exists()) {
            return response()->json(['message' => 'Already sent.'], 409);
        }

        $booking->applications()->create([
            'applicant_id' => $staff->id,
            'status'       => 'invited',
        ]);

        $staff->notify(new InvitationNotification($booking));

        return response()->json(['message' => 'Request sent.']);
    }
}
