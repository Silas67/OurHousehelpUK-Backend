<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $bookings = ServiceRequest::where('client_id', $userId)
            ->with('applicant:id,name,profile_photo_path')
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'active_bookings'   => $bookings->whereIn('status', ['open', 'matched', 'confirmed', 'active'])->count(),
            'completed'         => $bookings->where('status', 'completed')->count(),
            'credits_remaining' => 0,
        ];

        $formatted = $bookings->map(fn($b) => [
            'id'         => $b->id,
            'staff_name' => $b->applicant?->name,
            'role'       => $b->servicesSummary(),
            'date'       => $b->start_date?->toDateString(),
            'status'     => $b->status,
        ]);

        return response()->json(['stats' => $stats, 'bookings' => $formatted]);
    }
}
