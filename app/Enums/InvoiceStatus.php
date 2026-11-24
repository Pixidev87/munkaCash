<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unchecked = 'unchecked';
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public static function fromCheckedBalance(int $gross, int $outstanding): self
    {
        return match ($outstanding) {
            0 => self::Paid,
            $gross => self::Open,
            default => self::PartiallyPaid,
        };
    }
}
