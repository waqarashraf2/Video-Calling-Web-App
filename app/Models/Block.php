<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = [
        'blocker_session_id',
        'blocked_session_id',
        'blocker_fingerprint',
        'blocked_fingerprint',
        'expires_at',
    ];

    protected $hidden = ['blocker_fingerprint', 'blocked_fingerprint'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
