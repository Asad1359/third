<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int    $receiverId  Jis user ko event jana hai
     * @param  string $event       Event name: call.started, call.accepted,
     *                             call.rejected, call.ended, call.signal
     * @param  array  $payload     Event ke saath bhejne wala data
     */
    public function __construct(
        public int $receiverId,
        public string $event,
        public array $payload = []
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('call.user.' . $this->receiverId);
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}