<?php

namespace App\Enums;

enum InvoiceSource: string
{
    case Manual = 'manual';
    case Csv = 'csv';
}
