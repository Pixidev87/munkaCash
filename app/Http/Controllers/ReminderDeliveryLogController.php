<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminders\FilterReminderDeliveriesRequest;
use App\Models\ReminderTestDelivery;
use App\Services\ReminderDeliveryLogQuery;
use Inertia\Inertia;
use Inertia\Response;

class ReminderDeliveryLogController extends Controller
{
    public function index(FilterReminderDeliveriesRequest $request, string $organization, ReminderDeliveryLogQuery $log): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $filters = $request->criteria();
        $matching = $log->matching($company, $filters);
        $totals = $log->totals($matching);
        $deliveries = $matching
            ->select([
                'reminder_test_deliveries.id', 'reminder_test_deliveries.status', 'reminder_test_deliveries.reason',
                'reminder_test_deliveries.available_at', 'reminder_test_deliveries.created_at', 'reminder_test_deliveries.updated_at',
                'reminder_approvals.invoice_id', 'reminder_approvals.invoice_number',
            ])
            ->latest('reminder_test_deliveries.created_at')
            ->latest('reminder_test_deliveries.id')
            ->simplePaginate(20)
            ->appends($filters)
            ->through(fn (ReminderTestDelivery $delivery): array => [
                'id' => $delivery->id,
                'invoiceId' => (string) $delivery->getAttribute('invoice_id'),
                'invoiceNumber' => (string) $delivery->getAttribute('invoice_number'),
                'status' => $delivery->status->value,
                'reason' => $delivery->reason,
                'availableAt' => $delivery->available_at?->toISOString(),
                'createdAt' => $delivery->created_at->toISOString(),
                'updatedAt' => $delivery->updated_at->toISOString(),
            ]);

        return Inertia::render('organizations/reminder-delivery-log', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'filters' => [
                'status' => $filters['status'] ?? null,
                'invoiceNumber' => $filters['invoice_number'] ?? null,
                'reason' => $filters['reason'] ?? null,
                'queuedFrom' => $filters['queued_from'] ?? null,
                'queuedTo' => $filters['queued_to'] ?? null,
            ],
            'exportUrl' => $request->user()->can('exportData', $company)
                ? route('organizations.exports.reminder-deliveries', ['organization' => $company->id, ...$filters], absolute: false)
                : null,
            'totals' => $totals,
            'deliveries' => $deliveries,
        ]);
    }
}
