<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMemberRemoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public int $removedUserId,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        foreach ($this->conversation->members as $member) {
            $channels[] = new PrivateChannel('chat.user.' . $member->id);
        }

        // Also notify the removed user
        $channels[] = new PrivateChannel('chat.user.' . $this->removedUserId);

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'group.member.removed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->removedUserId,
        ];
    }
}