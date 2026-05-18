<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();

        $rows = UserNotification::query()
            ->where('user_id', $me->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        return response()->json([
            'data' => $rows->map(static function ($n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'payload' => $n->payload,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(404);
        }
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
