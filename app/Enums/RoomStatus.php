<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Expired = 'expired';
}
