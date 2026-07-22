<?php

namespace App\Console\Commands;

use App\Enums\GuestStatus;
use App\Enums\RoomStatus;
use App\Models\Block;
use App\Models\CallRoom;
use App\Models\GuestSession;
use App\Models\Report;
use App\Services\MatchmakingQueue;
use Illuminate\Console\Command;
use Throwable;

class CleanupVideoChatState extends Command
{
    protected $signature = 'videochat:cleanup';

    protected $description = 'Expire stale random video chat sessions, rooms, queue entries, reports, and blocks.';

    public function handle(): int
    {
        $stale = now()->subSeconds((int) config('videochat.heartbeat_ttl_seconds'));

        GuestSession::query()
            ->where('last_seen_at', '<', $stale)
            ->orWhere('expires_at', '<=', now())
            ->update(['status' => GuestStatus::Expired, 'expires_at' => now()]);

        CallRoom::query()
            ->where('status', RoomStatus::Active)
            ->where('started_at', '<', now()->subMinutes((int) config('videochat.room_ttl_minutes')))
            ->update(['status' => RoomStatus::Expired, 'ended_at' => now(), 'end_reason' => 'expired']);

        try {
            app(MatchmakingQueue::class)->drainExpired(fn (string $uuid) => GuestSession::query()
                ->where('public_uuid', $uuid)
                ->where('status', GuestStatus::Queued)
                ->where('expires_at', '>', now())
                ->exists());
        } catch (Throwable) {
            $this->warn('Optional cache queue cleanup skipped.');
        }

        Report::query()->where('expires_at', '<=', now())->delete();
        Block::query()->where('expires_at', '<=', now())->delete();

        $this->info('Video chat cleanup completed.');

        return self::SUCCESS;
    }
}
