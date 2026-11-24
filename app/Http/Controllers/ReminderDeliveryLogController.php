<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminders\FilterReminderDeliveriesRequest;
use App\Models\ReminderTestDelivery;
use Inertia\Inertia;
use Inertia\Response;

class ReminderDeliveryLogController extends Controller
{
    public function index(FilterReminderDeliveriesRequest $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $status = $request->validated('status');
        $invoiceNumber = $request->validated('invoice_number');

        $deliveries = ReminderTestDelivery::query()
            ->join('reminder_approvals', 'reminder_approvals.id', '=', 'reminder_test_deliveries.reminder_approval_id')
            ->whereIn('reminder_approvals.invoice_id', $company->invoices()->select('id'))
            ->when($invoiceNumber !== null, fn ($query) => $query->where('reminder_approvals.invoice_number', $invoiceNumber))
            ->when($status !== null, fn ($query) => $query->where('reminder_test_deliveries.status', $status))
            ->select([
                'reminder_test_deliveries.id', 'reminder_test_deliveries.status', 'reminder_test_deliveries.reason',
                'reminder_test_deliveries.created_at', 'reminder_test_deliveries.updated_at',
                'reminder_approvals.invoice_id', 'reminder_approvals.invoice_number',
            ])
            ->latest('reminder_test_deliveries.created_at')
            ->latest('reminder_test_deliveries.id')
            ->simplePaginate(20)
            ->appends(['status' => $status, 'invoice_number' => $invoiceNumber])
            ->through(fn (ReminderTestDelivery $delivery): array => [
                'id' => $delivery->id,
                'invoiceId' => (string) $delivery->getAttribute('invoice_id'),
                'invoiceNumber' => (string) $delivery->getAttribute('invoice_number'),
                'status' => $delivery->status->value,
                'reason' => $delivery->reason,
                'createdAt' => $delivery->created_at->toISOString(),
                'updatedAt' => $delivery->updated_at->toISOString(),
            ]);

        return Inertia::render('organizations/reminder-delivery-log', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'filters' => ['status' => $status, 'invoiceNumber' => $invoiceNumber],
            'deliveries' => $deliveries,
        ]);
    }
}
