<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendshipAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $accepter,
        public User $requester,
        public UserNotification $notification,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('social.'.$this->requester->id)];
    }

    public function broadcastAs(): string
    {
        return 'FriendshipAccepted';
    }

    public function broadcastWith(): array
    {
        $a = $this->accepter;

        return [
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'payload' => $this->notification->payload,
                'created_at' => $this->notification->created_at?->toIso8601String(),
                'read_at' => $this->notification->read_at?->toIso8601String(),
            ],
            'friend' => [
                'id' => $a->id,
                'nickname' => $a->nickname,
                'avatar_slug' => $a->avatar?->slug,
                'avatar_image_file' => $a->avatar?->image_file,
            ],
        ];
    }
}
