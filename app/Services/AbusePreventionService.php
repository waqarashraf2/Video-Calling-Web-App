<?php

namespace App\Services;

use App\Models\Block;
use App\Models\GuestSession;
use Illuminate\Http\Request;

class AbusePreventionService
{
    public function fingerprint(Request $request): string
    {
        $days = max(1, (int) config('videochat.fingerprint_rotation_days'));
        $bucket = intdiv(now()->timestamp, $days * 86400);
        $material = implode('|', [
            (string) $request->ip(),
            substr((string) $request->userAgent(), 0, 180),
            $request->headers->get('accept-language', ''),
            $bucket,
        ]);

        return hash_hmac('sha256', $material, (string) config('videochat.fingerprint_rotation_key'));
    }

    public function canMeet(GuestSession $first, GuestSession $second): bool
    {
        if ($first->is($second)) {
            return false;
        }

        return ! Block::query()
            ->where('expires_at', '>', now())
            ->where(function ($query) use ($first, $second) {
                $query->where([
                    'blocker_fingerprint' => $first->abuse_fingerprint,
                    'blocked_fingerprint' => $second->abuse_fingerprint,
                ])->orWhere([
                    'blocker_fingerprint' => $second->abuse_fingerprint,
                    'blocked_fingerprint' => $first->abuse_fingerprint,
                ]);
            })
            ->exists();
    }

    public function sanitizeText(?string $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
