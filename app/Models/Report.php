<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_session_id',
        'reported_session_id',
        'call_room_id',
        'reason',
        'description',
        'abuse_fingerprint',
        'status',
        'reviewed_at',
        'expires_at',
    ];

    protected $hidden = ['abuse_fingerprint'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(CallRoom::class, 'call_room_id');
    }
}
