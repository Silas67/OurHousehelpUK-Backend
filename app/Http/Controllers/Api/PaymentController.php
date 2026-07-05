<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\JobFilledNotification;
use App\Notifications\StaffConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * POST /payments/checkout
     *
     * Records payment and confirms the booking in one atomic transaction.
     * Stripe integration ready: swap status 'paid' for 'pending' and add
     * stripe_payment_intent_id once Stripe keys are live.
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'booking_id'   => ['required', 'integer', 'exists:service_requests,id'],
            'applicant_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $booking = ServiceRequest::findOrFail($request->booking_id);

        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (!in_array($booking->status, ['open', 'matched'])) {
            return response()->json(['message' => 'Booking cannot be confirmed at this stage.'], 422);
        }

        $application = $booking->applications()
            ->where('applicant_id', $request->applicant_id)
            ->where('status', 'pending')
            ->first();

        if (!$application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        // Derive amount from the cost breakdown saved on the booking
        $breakdown   = $booking->cost_breakdown ?? [];
        $amountPence = isset($breakdown['client_total'])
            ? (int) round((float) $breakdown['client_total'] * 100)
            : 0;

        DB::transaction(function () use ($booking, $application, $request, $amountPence) {
            Payment::create([
                'booking_id'   => $booking->id,
                'client_id'    => $request->user()->id,
                'amount_pence' => $amountPence,
                'currency'     => 'gbp',
                'status'       => 'paid',
            ]);

            $application->update(['status' => 'accepted']);
            $booking->applications()
                ->where('applicant_id', '!=', $request->applicant_id)
                ->update(['status' => 'rejected']);
            $booking->update(['status' => 'confirmed', 'applicant_id' => $request->applicant_id]);

            $selectedStaff = User::find($request->applicant_id);
            if ($selectedStaff) {
                $selectedStaff->notify(new StaffConfirmedNotification($booking));
            }

            $rejectedIds = $booking->applications()
                ->where('applicant_id', '!=', $request->applicant_id)
                ->pluck('applicant_id');
            User::whereIn('id', $rejectedIds)->each(
                fn($staff) => $staff->notify(new JobFilledNotification($booking))
            );
        });

        return response()->json([
            'message' => 'Payment recorded and booking confirmed.',
            'booking' => $booking->fresh(),
        ]);
    }

    /**
     * GET /payments/booking/{booking}
     * Returns payment record for a booking (client's own only).
     */
    public function forBooking(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $payment = Payment::where('booking_id', $booking->id)
            ->where('client_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json(['payment' => null]);
        }

        return response()->json([
            'payment' => [
                'id'              => $payment->id,
                'amount_pence'    => $payment->amount_pence,
                'amount_formatted'=> $payment->amount_formatted,
                'currency'        => $payment->currency,
                'status'          => $payment->status,
                'created_at'      => $payment->created_at->toDateTimeString(),
            ],
        ]);
    }
}
