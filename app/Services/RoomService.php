<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Enums\RoomStatus;
use App\Events\ParticipantLeft;
use App\Events\RoomEnded;
use App\Models\CallRoom;
use App\Models\GuestSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomService
{
    public function activeRoomFor(GuestSession $guest): ?CallRoom
    {
        return CallRoom::query()
            ->with(['firstGuest', 'secondGuest', 'initiator'])
            ->where('status', RoomStatus::Active)
            ->where(function ($query) use ($guest) {
                $query->where('first_guest_session_id', $guest->id)
                    ->orWhere('second_guest_session_id', $guest->id);
            })
            ->latest('id')
            ->first();
    }

    public function create(GuestSession $first, GuestSession $second): CallRoom
    {
        return DB::transaction(function () use ($first, $second) {
            $initiator = random_int(0, 1) === 1 ? $first : $second;

            $room = CallRoom::query()->create([
                'public_uuid' => (string) Str::uuid(),
                'first_guest_session_id' => $first->id,
                'second_guest_session_id' => $second->id,
                'initiator_guest_session_id' => $initiator->id,
                'status' => RoomStatus::Active,
                'started_at' => now(),
            ]);

            $first->forceFill(['status' => GuestStatus::Matched])->save();
            $second->forceFill(['status' => GuestStatus::Matched])->save();

            return $room->refresh()->load(['firstGuest', 'secondGuest', 'initiator']);
        });
    }

    public function end(CallRoom $room, GuestSession $actor, string $reason): void
    {
        if (! $room->contains($actor) || $room->status !== RoomStatus::Active) {
            return;
        }

        DB::transaction(function () use ($room, $reason) {
            $room->forceFill([
                'status' => RoomStatus::Ended,
                'ended_at' => now(),
                'end_reason' => $reason,
            ])->save();

            $room->firstGuest->forceFill(['status' => GuestStatus::Idle])->save();
            $room->secondGuest->forceFill(['status' => GuestStatus::Idle])->save();
        });

        $peer = $room->peerFor($actor);
        if ($peer) {
            broadcast(new ParticipantLeft($peer->public_uuid, $room->public_uuid, $reason))->toOthers();
        }
        broadcast(new RoomEnded($room->public_uuid, $reason))->toOthers();
    }
}
