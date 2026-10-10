<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationBlocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public string $blockedByName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.user.' . $this->conversation->user_id),
            new PrivateChannel('chat.admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.blocked';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->conversation->user_id,
            'is_blocked' => (bool) $this->conversation->is_blocked,
            'blocked_reason' => $this->conversation->blocked_reason,
            'blocked_by' => $this->blockedByName,
        ];
    }
}