<?php

use App\Enums\RoomStatus;
use App\Models\CallRoom;
use App\Services\GuestSessionService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('guest.{uuid}', function ($user, string $uuid) {
    $guest = app(GuestSessionService::class)->current();

    return $guest && hash_equals($guest->public_uuid, $uuid);
});

Broadcast::channel('room.{uuid}', function ($user, string $uuid) {
    $guest = app(GuestSessionService::class)->current();
    if (! $guest) {
        return false;
    }

    $room = CallRoom::query()
        ->with(['firstGuest', 'secondGuest'])
        ->where('public_uuid', $uuid)
        ->where('status', RoomStatus::Active)
        ->first();

    return $room && $room->contains($guest);
});
