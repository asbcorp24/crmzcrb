<?php

namespace App\Http\Controllers;

use App\Models\CrmNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $items = CrmNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'unread' => CrmNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'items' => $items,
        ]);
    }

    public function read(Request $request, CrmNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        if (!$notification->read_at) $notification->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request)
    {
        CrmNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
