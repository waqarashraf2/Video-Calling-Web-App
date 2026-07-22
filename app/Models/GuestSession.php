<?php

namespace App\Models;

use App\Enums\GuestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestSession extends Model
{
    protected $fillable = [
        'public_uuid',
        'display_name',
        'status',
        'abuse_fingerprint',
        'last_seen_at',
        'expires_at',
    ];

    protected $hidden = ['id', 'abuse_fingerprint'];

    protected function casts(): array
    {
        return [
            'status' => GuestStatus::class,
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function roomsAsFirst(): HasMany
    {
        return $this->hasMany(CallRoom::class, 'first_guest_session_id');
    }

    public function roomsAsSecond(): HasMany
    {
        return $this->hasMany(CallRoom::class, 'second_guest_session_id');
    }
}
