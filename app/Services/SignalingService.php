<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Events\ParticipantMediaStateChanged;
use App\Events\WebRtcSignalSent;
use App\Models\CallRoom;
use App\Models\GuestSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SignalingService
{
    public function __construct(private readonly SafeBroadcaster $broadcaster) {}

    public function send(GuestSession $sender, CallRoom $room, array $payload): void
    {
        $room->loadMissing(['firstGuest', 'secondGuest']);
        if ($room->status !== RoomStatus::Active || ! $room->contains($sender)) {
            throw ValidationException::withMessages(['room' => 'This room is not available for signaling.']);
        }

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > (int) config('videochat.signal_max_bytes')) {
            throw ValidationException::withMessages(['payload' => 'The signal payload is too large.']);
        }

        $peer = $room->peerFor($sender);
        if (! $peer) {
            throw ValidationException::withMessages(['room' => 'The sender is not a participant in this room.']);
        }

        if (($payload['type'] ?? null) === 'media-state') {
            $this->storeForPolling($peer, $room, $sender, $payload);
            $this->broadcaster->broadcast(new ParticipantMediaStateChanged($peer->public_uuid, $room->public_uuid, $payload));

            return;
        }

        $this->storeForPolling($peer, $room, $sender, $payload);
        $this->broadcaster->broadcast(new WebRtcSignalSent($peer->public_uuid, $room->public_uuid, $sender->public_uuid, $payload));
    }

    public function pendingFor(GuestSession $recipient, CallRoom $room, int $after = 0): array
    {
        $messages = Cache::get($this->pollingKey($recipient, $room), []);

        return array_values(array_filter($messages, fn (array $message) => $message['id'] > $after));
    }

    private function storeForPolling(GuestSession $recipient, CallRoom $room, GuestSession $sender, array $payload): void
    {
        $key = $this->pollingKey($recipient, $room);
        $messages = Cache::get($key, []);
        $messages[] = [
            'id' => (now()->getTimestampMs() * 1000) + random_int(100, 999),
            'room_uuid' => $room->public_uuid,
            'sender_uuid' => $sender->public_uuid,
            'signal' => $payload,
        ];

        Cache::put($key, array_slice($messages, -60), now()->addMinutes((int) config('videochat.room_ttl_minutes')));
    }

    private function pollingKey(GuestSession $recipient, CallRoom $room): string
    {
        return "videochat:signals:{$recipient->public_uuid}:{$room->public_uuid}";
    }
}
