<?php

namespace App\Http\Controllers;

use App\Models\ReminderApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderApprovalHistoryController extends Controller
{
    public function index(Request $request, string $organization, string $invoice): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $selected = $company->invoices()->findOrFail($invoice);
        $approvals = $selected->reminderApprovals()->getQuery()
            ->where('approved_at', '>', now()->subDays(ReminderApproval::RETENTION_DAYS))
            ->with(['approver:id,name', 'revoker:id,name', 'testDelivery:reminder_approval_id,status'])
            ->latest('id')
            ->simplePaginate(20)
            ->through(static fn (ReminderApproval $approval): array => [
                'id' => (string) $approval->id,
                'approvedBy' => $approval->approver?->name,
                'approvedAt' => $approval->approved_at->toISOString(),
                'expiresAt' => $approval->expires_at->toISOString(),
                'revokedBy' => $approval->revoker?->name,
                'revokedAt' => $approval->revoked_at?->toISOString(),
                'deliveryStatus' => $approval->testDelivery?->status->value,
            ]);

        return Inertia::render('organizations/reminder-approval-history', [
            'organizationId' => (string) $company->id,
            'invoiceId' => (string) $selected->id,
            'number' => $selected->invoice_number,
            'approvals' => $approvals,
        ]);
    }
}
