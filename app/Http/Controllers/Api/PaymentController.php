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
     * POST /payments/setup
     * Creates a Stripe Checkout Session in setup mode to save client's card.
     * App opens the URL in browser; webhook stores the payment method when saved.
     */
    public function setup(Request $request)
    {
        $request->validate([
            'booking_id' => ['required', 'integer', 'exists:service_requests,id'],
        ]);

        $booking = ServiceRequest::findOrFail($request->booking_id);
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $stripe  = $this->stripe();
        $user    = $request->user();
        $baseUrl = rtrim(config('app.url'), '/');

        if (!$user->stripe_customer_id) {
            $customer = $stripe->customers->create(['email' => $user->email, 'name' => $user->name]);
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        $session = $stripe->checkout->sessions->create([
            'mode'        => 'setup',
            'customer'    => $user->stripe_customer_id,
            'currency'    => 'gbp',
            'success_url' => $baseUrl . '/payment/card-saved?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $baseUrl . '/payment/cancel',
            'metadata'    => [
                'booking_id' => (string) $booking->id,
                'client_id'  => (string) $user->id,
            ],
        ]);

        return response()->json(['setup_url' => $session->url, 'session_id' => $session->id]);
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

        // quoted_pence is computed at booking creation from the actual
        // house_services.hourly_rate figures — cost_breakdown comes from a
        // separate, unused pricing engine where every base_cost is £0 and
        // would always produce a non-payable amount here.
        $amountPence = (int) ($booking->quoted_pence ?? 0);

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

            // ── Setup mode: save the payment method on the booking ──────────
            if ($session->mode === 'setup') {
                $bookingId = $meta->booking_id ?? null;
                if ($bookingId && $session->setup_intent) {
                    $stripe      = $this->stripe();
                    $setupIntent = $stripe->setupIntents->retrieve($session->setup_intent);
                    $pmId        = $setupIntent->payment_method;

                    if ($pmId) {
                        // Attach to customer so off_session charges work
                        $stripe->paymentMethods->attach($pmId, ['customer' => $session->customer]);
                        ServiceRequest::where('id', $bookingId)->update(['stripe_payment_method_id' => $pmId]);
                    }
                }
                return response()->json(['message' => 'ok']);
            }

            // ── Payment mode: existing checkout flow ────────────────────────
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
