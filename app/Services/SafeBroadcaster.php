<?php

namespace App\Services;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

class SafeBroadcaster
{
    public function broadcast(object $event, bool $toOthers = false): void
    {
        if (! config('videochat.realtime_broadcast')) {
            return;
        }

        try {
            $pending = broadcast($event);

            if ($toOthers) {
                $pending->toOthers();
            }

            unset($pending);
        } catch (BroadcastException $exception) {
            Log::warning('Realtime broadcast failed; continuing with polling fallback.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
