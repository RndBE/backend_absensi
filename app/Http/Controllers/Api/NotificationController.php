<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('employee_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function show(Request $request, $id)
    {
        $notification = Notification::where('employee_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $notification]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::where('employee_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Notifikasi telah dibaca']);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where('employee_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Semua notifikasi telah dibaca']);
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('employee_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }
}
