<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;

class ReminderDeliveryControls
{
    public const string ORGANIZATION_CLOSING = 'organization_closing';

    public const string ORGANIZATION_PAUSED = 'organization_paused';

    public const string CUSTOMER_BLOCKED = 'customer_do_not_contact';

    public const string INVOICE_DISPUTED = 'invoice_disputed';

    public const string BALANCE_UNVERIFIED = 'balance_unverified';

    public const string BILLING_CONTACT_CHANGED = 'billing_contact_changed';

    public const string BILLING_CONFIRMATION_REVOKED = 'billing_confirmation_revoked';

    public const string BALANCE_RECHECKED = 'balance_rechecked';

    public const string APPROVAL_REVOKED = 'approval_revoked';

    public const string DELIVERY_CANCELLED = 'delivery_cancelled';

    public const string INVOICE_DEADLINE_CHANGED = 'invoice_deadline_changed';

    public function blocker(Organization $company, Invoice $invoice): ?string
    {
        return match (true) {
            $company->isClosing() => self::ORGANIZATION_CLOSING,
            ! $company->reminders_enabled => self::ORGANIZATION_PAUSED,
            $invoice->is_disputed => self::INVOICE_DISPUTED,
            $invoice->customer->do_not_contact => self::CUSTOMER_BLOCKED,
            default => null,
        };
    }
}
