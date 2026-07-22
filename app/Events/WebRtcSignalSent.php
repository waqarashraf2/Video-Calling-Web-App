<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcSignalSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $recipientUuid,
        public string $roomUuid,
        public string $senderUuid,
        public array $signal,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('guest.'.$this->recipientUuid)];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal';
    }

    public function broadcastWith(): array
    {
        return ['room_uuid' => $this->roomUuid, 'sender_uuid' => $this->senderUuid, 'signal' => $this->signal];
    }
}
