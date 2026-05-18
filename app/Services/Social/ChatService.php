<?php

namespace App\Services\Social;

use App\Events\PrivateMessageReceived;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function __construct(
        private FriendshipService $friends,
    ) {}

    public function conversations(User $me): array
    {
        $friends = $this->friends->listFriends($me);
        if ($friends->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($friends as $f) {
            $fid = (int) $f->id;

            $last = PrivateMessage::query()
                ->where(
                    fn ($q) => $q->where(
                        fn ($qq) => $qq->where('sender_id', $me->id)->where('recipient_id', $fid),
                    )->orWhere(
                        fn ($qq) => $qq->where('sender_id', $fid)->where('recipient_id', $me->id),
                    ),
                )
                ->orderByDesc('id')
                ->first();

            $unreadCount = (int) PrivateMessage::query()
                ->where('recipient_id', $me->id)
                ->where('sender_id', $fid)
                ->whereNull('read_at')
                ->count();

            $out[] = [
                'user' => $f,
                'last_message_at' => $last?->created_at?->toIso8601String(),
                'last_message_preview' => $last ? \Illuminate\Support\Str::limit(trim((string) $last->body), 120) : null,
                'unread_from_peer' => $unreadCount,
            ];
        }

        usort($out, static function ($a, $b) {
            $ta = $a['last_message_at'] ?? '';
            $tb = $b['last_message_at'] ?? '';
            if ($ta === '' && $tb === '') {
                return strcmp($a['user']->nickname, $b['user']->nickname);
            }
            if ($tb === '') {
                return -1;
            }
            if ($ta === '') {
                return 1;
            }

            return strcmp((string) $tb, (string) $ta);
        });

        return $out;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, PrivateMessage> */
    public function messagesWith(User $me, User $peer, ?int $beforeId = null, int $limit = 40)
    {
        if (!$this->friends->areFriends($me, $peer)) {
            throw ValidationException::withMessages(['chat' => ['Só é possível conversar com amigos adicionados.']]);
        }
        if ($this->friends->blockedBetween($me, $peer)) {
            abort(403);
        }

        $q = PrivateMessage::query()
            ->where(fn ($qq) => $qq->where('sender_id', $me->id)->where('recipient_id', $peer->id)
                ->orWhere('sender_id', $peer->id)->where('recipient_id', $me->id))
            ->with(['sender:id,nickname'])
            ->orderByDesc('id');

        if ($beforeId !== null && $beforeId > 0) {
            $q->where('id', '<', $beforeId);
        }

        /** @phpstan-ignore-next-line */
        return $q->limit($limit)->get();
    }

    public function send(User $me, User $peer, string $body): PrivateMessage
    {
        $text = trim($body);
        if ($text === '') {
            throw ValidationException::withMessages(['body' => ['Escreva uma mensagem.']]);
        }

        $maxLen = min(6000, (int) config('game.social.dm_max_chars', 2000));
        if (mb_strlen($text) > $maxLen) {
            throw ValidationException::withMessages(['body' => ["Máximo de {$maxLen} caracteres."]]);
        }

        if (!$this->friends->areFriends($me, $peer)) {
            throw ValidationException::withMessages(['chat' => ['Só é possível mensagens entre amigos.']]);
        }
        if ($this->friends->blockedBetween($me, $peer)) {
            abort(403);
        }

        $msg = PrivateMessage::create([
            'sender_id' => $me->id,
            'recipient_id' => $peer->id,
            'body' => $text,
        ]);

        $notification = UserNotification::query()->create([
            'user_id' => $peer->id,
            'type' => UserNotification::TYPE_PRIVATE_MESSAGE,
            'payload' => [
                'message_id' => $msg->id,
                'sender_id' => $me->id,
                'sender_nickname' => $me->nickname,
                'preview' => \Illuminate\Support\Str::limit($text, 80),
            ],
        ]);

        $me->load('avatar');
        $msg->load('sender');
        event(new PrivateMessageReceived($msg->fresh(['sender.avatar']), $notification));

        return $msg;
    }

    public function markConversationRead(User $me, User $peer): void
    {
        if (!$this->friends->areFriends($me, $peer)) {
            return;
        }
        PrivateMessage::query()
            ->where('recipient_id', $me->id)
            ->where('sender_id', $peer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadMessagesCount(User $me): int
    {
        return (int) PrivateMessage::query()
            ->where('recipient_id', $me->id)
            ->whereNull('read_at')
            ->count();
    }
}
