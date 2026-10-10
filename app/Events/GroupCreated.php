<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatConversation $conversation) {}

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
        return 'group.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'name' => $this->conversation->name,
                'type' => 'group',
                'created_by' => $this->conversation->created_by,
                'members_count' => $this->conversation->members->count(),
            ],
        ];
    }
}