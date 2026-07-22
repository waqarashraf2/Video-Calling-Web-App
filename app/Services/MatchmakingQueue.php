<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MatchmakingQueue
{
    private string $key = 'videochat:queue';

    public function add(string $guestUuid): void
    {
        $this->withLock(function () use ($guestUuid) {
            $queue = $this->all();
            if (! in_array($guestUuid, $queue, true)) {
                $queue[] = $guestUuid;
            }
            Cache::store($this->store())->put($this->key, $queue, now()->addHours(2));
        });
    }

    public function remove(string $guestUuid): void
    {
        $this->withLock(function () use ($guestUuid) {
            $queue = array_values(array_filter($this->all(), fn ($uuid) => $uuid !== $guestUuid));
            Cache::store($this->store())->put($this->key, $queue, now()->addHours(2));
        });
    }

    public function drainExpired(callable $isValid): void
    {
        $this->withLock(function () use ($isValid) {
            Cache::store($this->store())->put(
                $this->key,
                array_values(array_filter($this->all(), $isValid)),
                now()->addHours(2)
            );
        });
    }

    public function all(): array
    {
        return Cache::store($this->store())->get($this->key, []);
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function lock(string $name = 'matchmaking')
    {
        return Cache::store($this->store())->lock("videochat:{$name}:lock", 10);
    }

    private function withLock(callable $callback): mixed
    {
        return $this->lock('queue')->block(5, $callback);
    }

    private function store(): ?string
    {
        return config('videochat.queue_store') ?: null;
    }
}
