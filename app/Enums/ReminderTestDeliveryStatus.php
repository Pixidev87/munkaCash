<?php

namespace App\Enums;

enum ReminderTestDeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Blocked = 'blocked';
    case Unknown = 'unknown';
}
