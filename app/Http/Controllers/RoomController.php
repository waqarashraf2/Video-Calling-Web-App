<?php

namespace App\Http\Controllers;

use App\Services\GuestSessionService;
use App\Services\MatchmakingService;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    public function leave(GuestSessionService $sessions, RoomService $rooms, MatchmakingService $matchmaking): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = $rooms->activeRoomFor($guest);

        if ($room) {
            $peer = $room->peerFor($guest);
            if ($peer) {
                $matchmaking->rememberCooldown($guest, $peer);
            }
            $rooms->end($room, $guest, 'left');
        }

        return response()->json(['ok' => true]);
    }

    public function next(GuestSessionService $sessions, RoomService $rooms, MatchmakingService $matchmaking): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = $rooms->activeRoomFor($guest);

        if ($room) {
            $peer = $room->peerFor($guest);
            if ($peer) {
                $matchmaking->rememberCooldown($guest, $peer);
            }
            $rooms->end($room, $guest, 'next');
        }

        return response()->json($matchmaking->join($guest));
    }

    public function retry(GuestSessionService $sessions, RoomService $rooms, MatchmakingService $matchmaking): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = $rooms->activeRoomFor($guest);

        if ($room) {
            $rooms->end($room, $guest, 'connection_failed');
        }

        return response()->json($matchmaking->join($guest));
    }
}
