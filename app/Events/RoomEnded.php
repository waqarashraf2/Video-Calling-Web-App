<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $roomUuid, public string $reason) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('room.'.$this->roomUuid)];
    }

    public function broadcastAs(): string
    {
        return 'room.ended';
    }

    public function broadcastWith(): array
    {
        return ['room_uuid' => $this->roomUuid, 'reason' => $this->reason];
    }
}
