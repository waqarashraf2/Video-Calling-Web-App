<?php

namespace App\Http\Controllers;

use App\Services\GuestSessionService;
use App\Services\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchmakingController extends Controller
{
    public function join(GuestSessionService $sessions, MatchmakingService $matchmaking): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');

        return response()->json($matchmaking->join($guest));
    }

    public function available(GuestSessionService $sessions, MatchmakingService $matchmaking): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');

        return response()->json([
            'participants' => $matchmaking->availableFor($guest),
        ]);
    }

    public function call(Request $request, GuestSessionService $sessions, MatchmakingService $matchmaking): JsonResponse
    {
        $data = $request->validate([
            'target_uuid' => ['required', 'uuid'],
        ]);

        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');

        return response()->json($matchmaking->call($guest, $data['target_uuid']));
    }

    public function leave(GuestSessionService $sessions, MatchmakingService $matchmaking): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $matchmaking->leave($guest);

        return response()->json(['ok' => true]);
    }
}
