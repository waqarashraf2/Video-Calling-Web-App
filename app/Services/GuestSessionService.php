<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Models\GuestSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class GuestSessionService
{
    public const SESSION_KEY = 'guest_session_uuid';

    public function __construct(private readonly AbusePreventionService $abuse) {}

    public function create(Request $request, string $displayName): GuestSession
    {
        Session::regenerate();

        $guest = GuestSession::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'display_name' => $this->abuse->sanitizeText($displayName, 30) ?? 'Guest',
            'status' => GuestStatus::Idle,
            'abuse_fingerprint' => $this->abuse->fingerprint($request),
            'last_seen_at' => now(),
            'expires_at' => now()->addMinutes((int) config('videochat.session_ttl_minutes')),
        ]);

        Session::put(self::SESSION_KEY, $guest->public_uuid);

        return $guest;
    }

    public function current(): ?GuestSession
    {
        $uuid = Session::get(self::SESSION_KEY);

        if (! $uuid) {
            return null;
        }

        return GuestSession::query()
            ->where('public_uuid', $uuid)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function touch(GuestSession $guest): GuestSession
    {
        $guest->forceFill([
            'last_seen_at' => now(),
            'expires_at' => now()->addMinutes((int) config('videochat.session_ttl_minutes')),
        ])->save();

        return $guest;
    }
}
