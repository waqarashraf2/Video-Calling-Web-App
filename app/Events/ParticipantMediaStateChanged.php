<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantMediaStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $recipientUuid, public string $roomUuid, public array $state) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guest.'.$this->recipientUuid)];
    }

    public function broadcastAs(): string
    {
        return 'participant.media-state';
    }

    public function broadcastWith(): array
    {
        return ['room_uuid' => $this->roomUuid, 'state' => $this->state];
    }
}
