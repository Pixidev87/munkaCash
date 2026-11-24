<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;

class ReminderDeliveryControls
{
    public const string ORGANIZATION_PAUSED = 'organization_paused';

    public const string CUSTOMER_BLOCKED = 'customer_do_not_contact';

    public function blocker(Organization $company, Invoice $invoice): ?string
    {
        return match (true) {
            ! $company->reminders_enabled => self::ORGANIZATION_PAUSED,
            $invoice->customer->do_not_contact => self::CUSTOMER_BLOCKED,
            default => null,
        };
    }
}
