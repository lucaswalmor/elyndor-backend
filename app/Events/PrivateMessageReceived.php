<?php

namespace App\Events;

use App\Models\PrivateMessage;
use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PrivateMessage $message,
        public UserNotification $notification,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('social.'.$this->message->recipient_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrivateMessageReceived';
    }

    public function broadcastWith(): array
    {
        $m = $this->message;

        return [
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'payload' => $this->notification->payload,
                'created_at' => $this->notification->created_at?->toIso8601String(),
                'read_at' => $this->notification->read_at?->toIso8601String(),
            ],
            'message' => [
                'id' => $m->id,
                'body' => $m->body,
                'sender_id' => $m->sender_id,
                'recipient_id' => $m->recipient_id,
                'created_at' => $m->created_at?->toIso8601String(),
                'sender' => [
                    'id' => $m->sender->id,
                    'nickname' => $m->sender->nickname,
                    'avatar_slug' => $m->sender->avatar?->slug ?? null,
                    'avatar_image_file' => $m->sender->avatar?->image_file ?? null,
                ],
            ],
        ];
    }
}
