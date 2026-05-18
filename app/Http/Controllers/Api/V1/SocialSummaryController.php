<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FriendRequest;
use App\Models\UserNotification;
use App\Services\Social\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialSummaryController extends Controller
{
    public function __construct(
        private ChatService $chat,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $me = $request->user();
        abort_unless($me, 401);

        $incomingFriends = FriendRequest::query()
            ->where('addressee_id', $me->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->count();

        $unreadNotifs = UserNotification::query()
            ->where('user_id', $me->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => [
                'incoming_friend_requests' => (int) $incomingFriends,
                'unread_notifications' => (int) $unreadNotifs,
                'unread_direct_messages' => $this->chat->unreadMessagesCount($me),
            ],
        ]);
    }
}
