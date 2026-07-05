<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request, ServiceRequest $booking)
    {
        if ($booking->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'You can only rate a completed booking.'], 422);
        }

        if (!$booking->applicant_id) {
            return response()->json(['message' => 'No staff to rate.'], 422);
        }

        $request->validate([
            'stars'   => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        // Upsert — allow re-rating
        Rating::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'client_id'    => $request->user()->id,
                'applicant_id' => $booking->applicant_id,
                'stars'        => $request->stars,
                'comment'      => $request->comment,
            ]
        );

        return response()->json(['message' => 'Thank you for your rating!']);
    }
}
