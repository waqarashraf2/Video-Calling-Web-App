<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\CallRoom;
use App\Services\GuestSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastAuthController extends Controller
{
    public function __invoke(Request $request, GuestSessionService $sessions): JsonResponse
    {
        $data = $request->validate([
            'socket_id' => ['required', 'string', 'max:80'],
            'channel_name' => ['required', 'string', 'max:160'],
        ]);

        $guest = $sessions->current();
        abort_unless($guest, 403);
        abort_unless($this->canAccess($guest->public_uuid, $data['channel_name']), 403);

        $signature = hash_hmac('sha256', $data['socket_id'].':'.$data['channel_name'], (string) env('REVERB_APP_SECRET'));

        return response()->json(['auth' => env('REVERB_APP_KEY').':'.$signature]);
    }

    private function canAccess(string $guestUuid, string $channel): bool
    {
        if ($channel === 'private-guest.'.$guestUuid) {
            return true;
        }

        if (! str_starts_with($channel, 'private-room.')) {
            return false;
        }

        $roomUuid = substr($channel, strlen('private-room.'));
        $room = CallRoom::query()
            ->with(['firstGuest', 'secondGuest'])
            ->where('public_uuid', $roomUuid)
            ->where('status', RoomStatus::Active)
            ->first();

        return $room && in_array($guestUuid, [$room->firstGuest->public_uuid, $room->secondGuest->public_uuid], true);
    }
}
