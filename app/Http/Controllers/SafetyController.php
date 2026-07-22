<?php

namespace App\Http\Controllers;

use App\Http\Requests\BlockRequest;
use App\Http\Requests\ReportRequest;
use App\Models\Block;
use App\Models\CallRoom;
use App\Models\Report;
use App\Services\AbusePreventionService;
use App\Services\GuestSessionService;
use App\Services\RoomService;
use Illuminate\Http\JsonResponse;

class SafetyController extends Controller
{
    public function report(ReportRequest $request, GuestSessionService $sessions, AbusePreventionService $abuse): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = $this->room($request->validated('room_uuid'));
        abort_unless($room->contains($guest), 403);
        $peer = $room->peerFor($guest);

        Report::query()->create([
            'reporter_session_id' => $guest->id,
            'reported_session_id' => $peer?->id,
            'call_room_id' => $room->id,
            'reason' => $request->validated('reason'),
            'description' => $abuse->sanitizeText($request->validated('description'), 500),
            'abuse_fingerprint' => $guest->abuse_fingerprint,
            'expires_at' => now()->addDays((int) config('videochat.report_retention_days')),
        ]);

        return response()->json(['ok' => true]);
    }

    public function block(BlockRequest $request, GuestSessionService $sessions, RoomService $rooms): JsonResponse
    {
        $guest = $sessions->current();
        abort_unless($guest, 401, 'No active guest session.');
        $room = $this->room($request->validated('room_uuid'));
        abort_unless($room->contains($guest), 403);
        $peer = $room->peerFor($guest);
        abort_unless($peer, 422);

        Block::query()->updateOrCreate([
            'blocker_fingerprint' => $guest->abuse_fingerprint,
            'blocked_fingerprint' => $peer->abuse_fingerprint,
        ], [
            'blocker_session_id' => $guest->id,
            'blocked_session_id' => $peer->id,
            'expires_at' => now()->addDays((int) config('videochat.block_retention_days')),
        ]);

        $rooms->end($room, $guest, 'blocked');

        return response()->json(['ok' => true]);
    }

    private function room(string $uuid): CallRoom
    {
        return CallRoom::query()->with(['firstGuest', 'secondGuest'])->where('public_uuid', $uuid)->firstOrFail();
    }
}
