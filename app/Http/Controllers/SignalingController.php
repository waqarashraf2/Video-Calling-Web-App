<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Http\Requests\SignalRequest;
use App\Models\CallRoom;
use App\Services\GuestSessionService;
use App\Services\SignalingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignalingController extends Controller
{
    public function index(Request $request, GuestSessionService $sessions, SignalingService $signaling): JsonResponse
    {
        $data = $request->validate([
            'room_uuid' => ['required', 'uuid'],
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = CallRoom::query()
            ->with(['firstGuest', 'secondGuest'])
            ->where('public_uuid', $data['room_uuid'])
            ->where('status', RoomStatus::Active)
            ->firstOrFail();
        abort_unless($room->contains($guest), 403);

        return response()->json([
            'signals' => $signaling->pendingFor($guest, $room, (int) ($data['after'] ?? 0)),
        ]);
    }

    public function store(SignalRequest $request, GuestSessionService $sessions, SignalingService $signaling): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = CallRoom::query()
            ->with(['firstGuest', 'secondGuest'])
            ->where('public_uuid', $request->validated('room_uuid'))
            ->firstOrFail();

        $signaling->send($guest, $room, [
            'type' => $request->validated('type'),
            'sequence' => $request->integer('sequence'),
            'payload' => $request->validated('payload'),
        ]);

        return response()->json(['ok' => true]);
    }
}
