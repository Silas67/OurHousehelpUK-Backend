<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ServiceRequest;
use App\Models\User;
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
            'service_types.*'    => ['string', 'in:cleaning,cooking,childcare,elderly_care,laundry,errands,pet_care'],
            'applicant_type'     => ['required', 'string', 'in:semi-live-in,live-out'],
            'management_plan'    => ['nullable', 'string', 'in:client-managed,company-managed'],
            'package_id'         => ['required_unless:duration_weeks,1', 'nullable', 'integer', 'exists:packages,id'],
            'apartment_type_id'  => ['nullable', 'integer', 'exists:apartment_types,id'],
            'address_line_1'     => ['required', 'string', 'max:255'],
            'address_line_2'     => ['nullable', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:255'],
            'postcode'           => ['required', 'string', 'max:10'],
            'start_date'         => ['required', 'date', 'after_or_equal:today'],
            'end_date'           => ['nullable', 'date', 'after:start_date'],
            'duration_weeks'     => ['required', 'integer', 'in:1,4,8,12'],
            'service_days'       => ['nullable', 'string', 'max:255'],
            'working_hour_start' => ['nullable', 'string', 'max:10'],
            'working_hour_end'   => ['nullable', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Calculate cost using the service
        $managementPlan = $request->management_plan ?? 'company-managed';
        $costService = new BookingCostService();
        $pkg         = $request->package_id ? Package::find($request->package_id) : null;
        $costResult  = $costService->calculate(
            $request->service_types,
            $request->package_id,
            $managementPlan,
            $request->duration_weeks,
            $request->apartment_type_id
        );

        $payRate = $costResult['staff_salary'] > 0
            ? BookingCostService::format($costResult['staff_salary'])
            : null;

        $booking = ServiceRequest::create([
            'client_id'          => $request->user()->id,
            'service_types'      => $request->service_types,
            'applicant_type'     => $request->applicant_type,
            'management_plan'    => $managementPlan,
            'apartment_type_id'  => $request->apartment_type_id,
            'feature_cost'       => $costResult['apartment_cost'],
            'address_line_1'     => $request->address_line_1,
            'address_line_2'     => $request->address_line_2,
            'city'               => $request->city,
            'postcode'           => strtoupper(trim($request->postcode)),
            'start_date'         => $request->start_date,
            'end_date'           => $request->end_date,
            'duration_weeks'     => $request->duration_weeks,
            'package_name'       => $pkg?->name,
            'days_per_week'      => $pkg?->days_per_week,
            'service_days'       => $request->service_days,
            'working_hour_start' => $request->working_hour_start,
            'working_hour_end'   => $request->working_hour_end,
            'pay_rate'           => $payRate,
            'cost_breakdown'     => $costResult,
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
        $booking->update(['status' => 'completed']);
        return response()->json(['message' => 'Booking marked as completed.', 'booking' => $booking->fresh()]);
    }
}
