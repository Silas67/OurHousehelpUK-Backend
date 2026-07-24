<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseService;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\JobFilledNotification;
use App\Notifications\StaffConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class PaymentController extends Controller
{
    private function stripe(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    /**
     * POST /payments/checkout
     * Creates a Stripe Checkout Session and returns the URL for the app to open in-browser.
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

        // Prefer the amount stored at booking creation, but recompute from the
        // booking's own fields if it's missing or zero (older bookings, or any
        // created before the amount was computed) so checkout never dead-ends.
        $amountPence = (int) ($booking->quoted_pence ?? 0);
        if ($amountPence <= 0) {
            $amountPence = $this->amountPenceFor($booking);
        }

        if ($amountPence <= 0) {
            return response()->json(['message' => 'Booking has no payable amount.'], 422);
        }

        $stripe  = $this->stripe();
        $baseUrl = rtrim(config('app.url'), '/');

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'line_items'           => [[
                'price_data' => [
                    'currency'     => 'gbp',
                    'unit_amount'  => $amountPence,
                    'product_data' => [
                        'name'        => 'OurHouseHelp Booking',
                        'description' => $booking->servicesSummary() . ' — ' . ($booking->package_name ?? 'One-time booking'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $baseUrl . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $baseUrl . '/payment/cancel',
            'metadata'    => [
                'booking_id'   => (string) $booking->id,
                'applicant_id' => (string) $request->applicant_id,
                'client_id'    => (string) $request->user()->id,
            ],
        ]);

        Payment::create([
            'booking_id'               => $booking->id,
            'client_id'                => $request->user()->id,
            'amount_pence'             => $amountPence,
            'currency'                 => 'gbp',
            'status'                   => 'pending',
            'stripe_payment_intent_id' => $session->id,
        ]);

        return response()->json([
            'checkout_url' => $session->url,
            'session_id'   => $session->id,
        ]);
    }

    /** Recompute the charge from a booking's fields — mirrors BookingController::store. */
    private function amountPenceFor(ServiceRequest $booking): int
    {
        $services  = HouseService::whereIn('slug', $booking->service_types ?? [])->get();
        $avgRate   = $services->isNotEmpty() ? $services->avg('hourly_rate') : 14.0;
        $sessHours = $booking->applicant_type === 'semi-live-in'
            ? 10
            : ((float) $booking->hours_per_session ?: 3);
        $daysPerWk = max(1, (int) ($booking->days_per_week ?? 1));
        $isOneOff  = (int) $booking->duration_weeks === 1 && $booking->applicant_type !== 'semi-live-in';
        $totalSessions = $isOneOff ? 1 : ($daysPerWk * (int) $booking->duration_weeks);

        return (int) round($avgRate * $sessHours * $totalSessions * 100);
    }

    /**
     * POST /stripe/webhook
     * Stripe calls this when checkout.session.completed fires.
     * No Sanctum auth — verified by Stripe signature.
     */
    public function webhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $meta    = $session->metadata;

            $bookingId   = $meta->booking_id ?? null;
            $applicantId = $meta->applicant_id ?? null;

            if (!$bookingId || !$applicantId) {
                return response()->json(['message' => 'ok'], 200);
            }

            $booking = ServiceRequest::find($bookingId);
            if (!$booking || !in_array($booking->status, ['open', 'matched'])) {
                return response()->json(['message' => 'ok'], 200);
            }

            $application = $booking->applications()
                ->where('applicant_id', $applicantId)
                ->where('status', 'pending')
                ->first();

            if (!$application) {
                return response()->json(['message' => 'ok'], 200);
            }

            DB::transaction(function () use ($booking, $application, $applicantId, $session) {
                Payment::where('stripe_payment_intent_id', $session->id)
                    ->update(['status' => 'paid']);

                $application->update(['status' => 'accepted']);
                $booking->applications()
                    ->where('applicant_id', '!=', $applicantId)
                    ->update(['status' => 'rejected']);
                $booking->update(['status' => 'confirmed', 'applicant_id' => $applicantId]);

                $selectedStaff = User::find($applicantId);
                if ($selectedStaff) {
                    $selectedStaff->notify(new StaffConfirmedNotification($booking));
                }

                $rejectedIds = $booking->applications()
                    ->where('applicant_id', '!=', $applicantId)
                    ->pluck('applicant_id');
                User::whereIn('id', $rejectedIds)->each(
                    fn($staff) => $staff->notify(new JobFilledNotification($booking))
                );
            });
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * GET /payments/booking/{booking}
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
                'id'               => $payment->id,
                'amount_pence'     => $payment->amount_pence,
                'amount_formatted' => $payment->amount_formatted,
                'currency'         => $payment->currency,
                'status'           => $payment->status,
                'created_at'       => $payment->created_at->toDateTimeString(),
            ],
        ]);
    }
}
