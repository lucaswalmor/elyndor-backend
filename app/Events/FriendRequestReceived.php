<?php

namespace App\Events;

use App\Models\FriendRequest;
use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FriendRequest $request,
        public UserNotification $notification,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('social.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'FriendRequestReceived';
    }

    public function broadcastWith(): array
    {
        $r = $this->request;

        return [
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'payload' => $this->notification->payload,
                'created_at' => $this->notification->created_at?->toIso8601String(),
                'read_at' => $this->notification->read_at?->toIso8601String(),
            ],
            'request' => [
                'id' => $r->id,
                'status' => $r->status,
                'requester' => [
                    'id' => $r->requester->id,
                    'nickname' => $r->requester->nickname,
                    'avatar_slug' => $r->requester->avatar?->slug,
                    'avatar_image_file' => $r->requester->avatar?->image_file,
                ],
            ],
        ];
    }
}
