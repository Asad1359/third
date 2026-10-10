<?php

namespace App\Events;

use App\Models\ChatRating;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatRated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatRating $rating) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.admin')];
    }

    public function broadcastAs(): string
    {
        return 'chat.rated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->rating->conversation_id,
            'user_id' => $this->rating->user_id,
            'rating' => $this->rating->rating,
            'feedback' => $this->rating->feedback,
        ];
    }
}