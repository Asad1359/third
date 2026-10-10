<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatConversation $conversation) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.admin')];
    }

    public function broadcastAs(): string
    {
        return 'conversation.created';
    }

    public function broadcastWith(): array
    {
        $this->conversation->loadMissing('user');

        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'user_id' => $this->conversation->user_id,
                'user' => $this->conversation->user?->only(['id', 'name', 'email']),
                'last_message' => $this->conversation->last_message,
                'unread_admin' => $this->conversation->unread_admin,
                'unread_user' => $this->conversation->unread_user,
                'created_at' => $this->conversation->created_at?->toISOString(),
            ],
        ];
    }
}