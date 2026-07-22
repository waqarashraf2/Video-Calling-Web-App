<?php

namespace App\Enums;

enum ReportReason: string
{
    case Nudity = 'nudity_or_sexual_content';
    case Harassment = 'harassment_or_threats';
    case Hate = 'hate_speech';
    case Minor = 'suspected_minor';
    case Spam = 'spam_or_scam';
    case Dangerous = 'dangerous_or_illegal_behavior';
    case Other = 'other';
}
