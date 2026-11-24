<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ReminderApproval;

class ReminderApprovalStatus
{
    /** @return 'expired'|'invalidated'|'active' */
    public function evaluate(Organization $company, ReminderApproval $approval, ?string $fingerprint): string
    {
        if ($approval->expires_at->lte(now())) {
            return 'expired';
        }

        if ($fingerprint === null
            || ! hash_equals($approval->fingerprint, $fingerprint)
            || $approval->approver?->cannot('approveReminder', $company) !== false) {
            return 'invalidated';
        }

        return 'active';
    }
}
