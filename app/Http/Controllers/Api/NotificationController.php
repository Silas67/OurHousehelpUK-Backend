<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? null,
                'title'      => $n->data['title'] ?? null,
                'body'       => $n->data['body'] ?? null,
                'booking_id' => $n->data['booking_id'] ?? null,
                'staff_id'   => $n->data['staff_id'] ?? null,
                'read'       => !is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        $unreadCount = $request->user()->unreadNotifications()->count();

        return response()->json(['notifications' => $notifications, 'unread_count' => $unreadCount]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->find($id);
        if ($notification) $notification->markAsRead();
        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All marked as read.']);
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
    }
}
