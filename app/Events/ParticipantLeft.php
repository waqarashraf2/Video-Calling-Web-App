<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantLeft implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $recipientUuid, public string $roomUuid, public string $reason) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guest.'.$this->recipientUuid)];
    }

    public function broadcastAs(): string
    {
        return 'participant.left';
    }

    public function broadcastWith(): array
    {
        return ['room_uuid' => $this->roomUuid, 'reason' => $this->reason];
    }
}
