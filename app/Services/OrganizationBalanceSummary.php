<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Organization;

class OrganizationBalanceSummary
{
    /** @return array{outstanding: string, disputed: string, unchecked: int} */
    public function forOrganization(Organization $company): array
    {
        $openStatuses = [InvoiceStatus::Open->value, InvoiceStatus::PartiallyPaid->value];
        $balances = $company->invoices()->toBase()
            ->where('currency', 'HUF')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_checked_at IS NOT NULL AND status IN (?, ?) AND is_disputed = ? THEN outstanding_amount END), 0) AS outstanding',
                [...$openStatuses, false],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status_checked_at IS NOT NULL AND status IN (?, ?) AND is_disputed = ? THEN outstanding_amount END), 0) AS disputed',
                [...$openStatuses, true],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status_checked_at IS NULL OR outstanding_amount IS NULL THEN 1 END) AS unchecked',
            )
            ->first();

        return [
            'outstanding' => (string) ($balances->outstanding ?? 0),
            'disputed' => (string) ($balances->disputed ?? 0),
            'unchecked' => (int) ($balances->unchecked ?? 0),
        ];
    }
}
