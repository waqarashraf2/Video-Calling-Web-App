<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Enums\RoomStatus;
use App\Events\MatchFound;
use App\Models\CallRoom;
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
        private readonly SafeBroadcaster $broadcaster,
    ) {}

    public function join(GuestSession $guest): array
    {
        $this->expireStaleState();

        $activeRoom = $this->rooms->activeRoomFor($guest);
        if ($activeRoom) {
            return [
                'matched' => true,
                'available' => $this->availableCount($guest),
                'room' => $activeRoom->public_uuid,
            ];
        }

        $guest = DB::transaction(function () use ($guest) {
            $guest = GuestSession::query()->lockForUpdate()->findOrFail($guest->id);

            $guest->forceFill([
                'status' => GuestStatus::Queued,
                'last_seen_at' => now(),
            ])->save();

            return $guest;
        });

        return [
            'matched' => false,
            'available' => $this->availableCount($guest),
            'room' => null,
        ];
    }

    public function availableFor(GuestSession $guest): array
    {
        $this->expireStaleState();

        $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

        return GuestSession::query()
            ->whereKeyNot($guest->id)
            ->where('status', GuestStatus::Queued)
            ->where('expires_at', '>', now())
            ->where('last_seen_at', '>=', $freshAfter)
            ->latest('last_seen_at')
            ->limit(25)
            ->get()
            ->filter(fn (GuestSession $candidate) => $candidate->abuse_fingerprint !== $guest->abuse_fingerprint
                && $this->abuse->canMeet($guest, $candidate)
                && ! $this->isCoolingDown($guest, $candidate))
            ->unique('abuse_fingerprint')
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
        $this->expireStaleState();

        $matchedRoom = DB::transaction(function () use ($guest, $targetUuid) {
            $guest = GuestSession::query()->lockForUpdate()->findOrFail($guest->id);
            $target = GuestSession::query()
                ->where('public_uuid', $targetUuid)
                ->lockForUpdate()
                ->first();

            if (! $target || $target->is($guest)) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is no longer available.']);
            }

            if ($target->abuse_fingerprint === $guest->abuse_fingerprint) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is another tab from your device.']);
            }

            $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));
            if ($target->expires_at <= now() || $target->last_seen_at < $freshAfter) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant went offline.']);
            }

            if ($target->status !== GuestStatus::Queued) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is not available now.']);
            }

            if ($guest->status === GuestStatus::Matched) {
                throw ValidationException::withMessages(['target_uuid' => 'You are already in a call.']);
            }

            if ($guest->status !== GuestStatus::Queued) {
                $guest->forceFill([
                    'status' => GuestStatus::Queued,
                    'last_seen_at' => now(),
                ])->save();
            }

            if (! $this->abuse->canMeet($guest, $target) || $this->isCoolingDown($guest, $target)) {
                throw ValidationException::withMessages(['target_uuid' => 'This participant is not available for safety reasons.']);
            }

            return $this->rooms->create($guest, $target);
        });

        foreach ([$matchedRoom->firstGuest, $matchedRoom->secondGuest] as $participant) {
            $this->broadcaster->broadcast(new MatchFound($participant, $matchedRoom));
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
        $this->expireStaleState();

        $freshAfter = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

        return GuestSession::query()
            ->when($guest, fn ($query) => $query->whereKeyNot($guest->id))
            ->where('status', GuestStatus::Queued)
            ->where('expires_at', '>', now())
            ->where('last_seen_at', '>=', $freshAfter)
            ->distinct('abuse_fingerprint')
            ->count('abuse_fingerprint');
    }

    public function expireStaleState(): void
    {
        $stale = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

        GuestSession::query()
            ->where(function ($query) use ($stale) {
                $query->where('last_seen_at', '<', $stale)
                    ->orWhere('expires_at', '<=', now());
            })
            ->whereIn('status', [GuestStatus::Idle, GuestStatus::Queued, GuestStatus::Matched])
            ->update(['status' => GuestStatus::Expired, 'expires_at' => now()]);

        CallRoom::query()
            ->with(['firstGuest', 'secondGuest'])
            ->where('status', RoomStatus::Active)
            ->where(function ($query) use ($stale) {
                $query->whereHas('firstGuest', function ($query) use ($stale) {
                    $query->where('last_seen_at', '<', $stale)
                        ->orWhere('expires_at', '<=', now());
                })->orWhereHas('secondGuest', function ($query) use ($stale) {
                    $query->where('last_seen_at', '<', $stale)
                        ->orWhere('expires_at', '<=', now());
                });
            })
            ->get()
            ->each(function (CallRoom $room): void {
                $room->forceFill([
                    'status' => RoomStatus::Expired,
                    'ended_at' => now(),
                    'end_reason' => 'stale_participant',
                ])->save();

                foreach ([$room->firstGuest, $room->secondGuest] as $participant) {
                    if ($participant->status !== GuestStatus::Expired) {
                        $participant->forceFill(['status' => GuestStatus::Idle])->save();
                    }
                }
            });
    }
}
