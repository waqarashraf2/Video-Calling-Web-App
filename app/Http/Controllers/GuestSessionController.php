<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuestSessionRequest;
use App\Models\GuestSession;
use App\Services\GuestSessionService;
use App\Services\MatchmakingService;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;

class GuestSessionController extends Controller
{
    public function store(StoreGuestSessionRequest $request, GuestSessionService $sessions): JsonResponse
    {
        $guest = $sessions->create($request, $request->validated('display_name'));

        return response()->json(['session' => [
            'uuid' => $guest->public_uuid,
            'display_name' => $guest->display_name,
            'status' => $guest->status->value,
        ], 'csrf_token' => csrf_token()], 201);
    }

    public function heartbeat(GuestSessionService $sessions): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $sessions->touch($guest);

        return response()->json(['ok' => true]);
    }

    public function state(GuestSessionService $sessions, RoomService $rooms): JsonResponse
    {
        $guest = $sessions->current();
        if (! $guest) {
            return response()->json(['session' => null]);
        }

        $room = $rooms->activeRoomFor($guest);

        return response()->json([
            'session' => ['uuid' => $guest->public_uuid, 'display_name' => $guest->display_name, 'status' => $guest->status->value],
            'room' => $room ? [
                'uuid' => $room->public_uuid,
                'room_uuid' => $room->public_uuid,
                'peer' => ['uuid' => $room->peerFor($guest)?->public_uuid, 'display_name' => $room->peerFor($guest)?->display_name],
                'initiator' => $room->initiator_guest_session_id === $guest->id,
                'ice_servers' => array_values(array_filter(config('webrtc.ice_servers'), fn ($server) => ! empty($server['urls']))),
                'connection_timeout_seconds' => config('webrtc.connection_timeout_seconds'),
            ] : null,
        ]);
    }

    public function online(GuestSessionService $sessions, MatchmakingService $matchmaking): JsonResponse
    {
        $matchmaking->expireStaleState();

        $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));
        $guest = $sessions->current();

        return response()->json([
            'online' => GuestSession::query()
                ->where('expires_at', '>', now())
                ->where('last_seen_at', '>=', $freshAfter)
                ->distinct('abuse_fingerprint')
                ->count('abuse_fingerprint'),
            'waiting' => $matchmaking->availableCount($guest),
        ]);
    }
}
