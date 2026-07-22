<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRoom extends Model
{
    protected $fillable = [
        'public_uuid',
        'first_guest_session_id',
        'second_guest_session_id',
        'initiator_guest_session_id',
        'status',
        'started_at',
        'ended_at',
        'end_reason',
    ];

    protected $hidden = ['id', 'first_guest_session_id', 'second_guest_session_id', 'initiator_guest_session_id'];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function firstGuest(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class, 'first_guest_session_id');
    }

    public function secondGuest(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class, 'second_guest_session_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class, 'initiator_guest_session_id');
    }

    public function contains(GuestSession $guest): bool
    {
        return $guest->is($this->firstGuest) || $guest->is($this->secondGuest);
    }

    public function peerFor(GuestSession $guest): ?GuestSession
    {
        if ($guest->is($this->firstGuest)) {
            return $this->secondGuest;
        }

        if ($guest->is($this->secondGuest)) {
            return $this->firstGuest;
        }

        return null;
    }
}
