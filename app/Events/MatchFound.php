<?php

namespace App\Events;

use App\Models\CallRoom;
use App\Models\GuestSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchFound implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GuestSession $recipient, public CallRoom $room) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guest.'.$this->recipient->public_uuid)];
    }

    public function broadcastAs(): string
    {
        return 'match.found';
    }

    public function broadcastWith(): array
    {
        $peer = $this->room->peerFor($this->recipient);

        return [
            'room_uuid' => $this->room->public_uuid,
            'peer' => ['uuid' => $peer?->public_uuid, 'display_name' => $peer?->display_name],
            'initiator' => $this->room->initiator_guest_session_id === $this->recipient->id,
            'ice_servers' => array_values(array_filter(config('webrtc.ice_servers'), fn ($server) => ! empty($server['urls']))),
            'connection_timeout_seconds' => config('webrtc.connection_timeout_seconds'),
        ];
    }
}
