<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomEnded implements ShouldBroadcast
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
