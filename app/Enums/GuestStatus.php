<?php

namespace App\Enums;

enum GuestStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Matched = 'matched';
    case Offline = 'offline';
    case Expired = 'expired';
}
