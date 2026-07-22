<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Events\MatchFound;
use App\Models\GuestSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchmakingService
{
    public function __construct(
        private readonly MatchmakingQueue $queue,
        private readonly RoomService $rooms,
        private readonly AbusePreventionService $abuse,
    ) {}

    public function join(GuestSession $guest): array
    {
        $activeRoom = $this->rooms->activeRoomFor($guest);
        if ($activeRoom) {
            return [
                'matched' => true,
                'available' => $this->availableCount($guest),
                'room' => $activeRoom->public_uuid,
            ];
        }

        $matchedRoom = DB::transaction(function () use ($guest) {
            $guest = GuestSession::query()->lockForUpdate()->findOrFail($guest->id);
            $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

            $candidate = GuestSession::query()
                ->whereKeyNot($guest->id)
                ->where('status', GuestStatus::Queued)
                ->where('expires_at', '>', now())
                ->where('last_seen_at', '>=', $freshAfter)
                ->oldest('last_seen_at')
                ->lockForUpdate()
                ->get()
                ->first(fn (GuestSession $candidate) => $this->abuse->canMeet($guest, $candidate) && ! $this->isCoolingDown($guest, $candidate));

            if ($candidate) {
                return $this->rooms->create($candidate, $guest);
            }

            $guest->forceFill([
                'status' => GuestStatus::Queued,
                'last_seen_at' => now(),
            ])->save();

            return null;
        });

        if ($matchedRoom) {
            foreach ([$matchedRoom->firstGuest, $matchedRoom->secondGuest] as $participant) {
                broadcast(new MatchFound($participant, $matchedRoom));
            }
        }

        return [
            'matched' => (bool) $matchedRoom,
            'available' => $this->availableCount($guest),
            'room' => $matchedRoom?->public_uuid,
        ];
    }

    public function availableFor(GuestSession $guest): array
    {
        $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

        return GuestSession::query()
            ->whereKeyNot($guest->id)
            ->where('status', GuestStatus::Queued)
            ->where('expires_at', '>', now())
            ->where('last_seen_at', '>=', $freshAfter)
            ->latest('last_seen_at')
            ->limit(25)
            ->get()
            ->filter(fn (GuestSession $candidate) => $this->abuse->canMeet($guest, $candidate) && ! $this->isCoolingDown($guest, $candidate))
            ->map(fn (GuestSession $candidate) => [
                'uuid' => $candidate->public_uuid,
                'display_name' => $candidate->display_name,
                'last_seen_seconds_ago' => max(0, (int) $candidate->last_seen_at?->diffInSeconds(now())),
            ])
            ->values()
            ->all();
    }

    public function call(GuestSession $guest, string $targetUuid): array
    {
        $matchedRoom = DB::transaction(function () use ($guest, $targetUuid) {
            $guest = GuestSession::query()->lockForUpdate()->findOrFail($guest->id);
            $target = GuestSession::query()
                ->where('public_uuid', $targetUuid)
                ->lockForUpdate()
                ->first();

            if (! $target || $target->is($guest)) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is no longer available.']);
            }

            if ($guest->status !== GuestStatus::Queued || $target->status !== GuestStatus::Queued) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is already in another call.']);
            }

            $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));
            if ($target->expires_at <= now() || $target->last_seen_at < $freshAfter) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant went offline.']);
            }

            if (! $this->abuse->canMeet($guest, $target) || $this->isCoolingDown($guest, $target)) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is not available for safety reasons.']);
            }

            return $this->rooms->create($guest, $target);
        });

        foreach ([$matchedRoom->firstGuest, $matchedRoom->secondGuest] as $participant) {
            broadcast(new MatchFound($participant, $matchedRoom));
        }

        return [
            'matched' => true,
            'available' => $this->availableCount($guest),
            'room' => $matchedRoom->public_uuid,
        ];
    }

    public function leave(GuestSession $guest): void
    {
        if ($guest->status === GuestStatus::Queued) {
            $guest->forceFill(['status' => GuestStatus::Idle])->save();
        }
    }

    public function rememberCooldown(GuestSession $first, GuestSession $second): void
    {
        $seconds = (int) config('videochat.rematch_cooldown_seconds');
        Cache::put($this->cooldownKey($first, $second), true, now()->addSeconds($seconds));
    }

    private function isCoolingDown(GuestSession $first, GuestSession $second): bool
    {
        return Cache::has($this->cooldownKey($first, $second));
    }

    private function cooldownKey(GuestSession $first, GuestSession $second): string
    {
        $pair = [$first->public_uuid, $second->public_uuid];
        sort($pair);

        return 'videochat:cooldown:'.implode(':', $pair);
    }

    public function availableCount(?GuestSession $guest = null): int
    {
        $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

        return GuestSession::query()
            ->when($guest, fn ($query) => $query->whereKeyNot($guest->id))
            ->where('status', GuestStatus::Queued)
            ->where('expires_at', '>', now())
            ->where('last_seen_at', '>=', $freshAfter)
            ->count();
    }
}
