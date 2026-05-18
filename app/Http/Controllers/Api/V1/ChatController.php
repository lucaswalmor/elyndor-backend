<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SocialUserResource;
use App\Models\User;
use App\Services\Social\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chat,
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $items = [];
        foreach ($this->chat->conversations($request->user()) as $row) {
            /** @var \App\Models\User $u */
            $u = $row['user'];

            $items[] = [
                'user' => (new SocialUserResource($u))->resolve(),
                'last_message_at' => $row['last_message_at'],
                'last_message_preview' => $row['last_message_preview'],
                'unread_from_peer' => $row['unread_from_peer'],
            ];
        }

        return response()->json(['data' => $items]);
    }

    public function messages(Request $request, User $user): JsonResponse
    {
        $beforeId = null;
        if ($request->query('before')) {
            $beforeId = (int) $request->query('before');
            $beforeId = $beforeId > 0 ? $beforeId : null;
        }

        $limit = min(80, max(10, (int) $request->query('limit', 40)));

        $messages = $this->chat->messagesWith($request->user(), $user, $beforeId, $limit);
        /** @phpstan-ignore-next-line */
        $this->chat->markConversationRead($request->user(), $user);

        $data = [];
        foreach ($messages as $m) {
            $data[] = [
                'id' => $m->id,
                'body' => $m->body,
                'sender_id' => $m->sender_id,
                'recipient_id' => $m->recipient_id,
                'created_at' => $m->created_at?->toIso8601String(),
                /** @phpstan-ignore-next-line */
                'sender' => ['nickname' => $m->sender?->nickname ?? ''],
            ];
        }

        return response()->json(['data' => array_reverse($data)]);
    }

    public function send(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $msg = $this->chat->send($request->user(), $user, (string) $data['body']);

        return response()->json([
            'data' => [
                'id' => $msg->id,
                'body' => $msg->body,
                'sender_id' => $msg->sender_id,
                'recipient_id' => $msg->recipient_id,
                'created_at' => $msg->created_at?->toIso8601String(),
                'sender' => ['nickname' => $msg->sender?->nickname ?? ''],
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function markRead(Request $request, User $user): JsonResponse
    {
        $this->chat->markConversationRead($request->user(), $user);

        return response()->json(['data' => ['ok' => true]]);
    }
}
