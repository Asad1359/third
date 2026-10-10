<?php

namespace App\Events;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMemberAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public User $newMember,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        foreach ($this->conversation->members as $member) {
            $channels[] = new PrivateChannel('chat.user.' . $member->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'group.member.added';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'member' => [
                'id' => $this->newMember->id,
                'name' => $this->newMember->name,
            ],
        ];
    }
}