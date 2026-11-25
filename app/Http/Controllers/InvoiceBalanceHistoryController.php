<?php

namespace App\Http\Controllers;

use App\Models\InvoiceBalanceEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceBalanceHistoryController extends Controller
{
    public function index(Request $request, string $organization, string $invoice): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $selected = $company->invoices()->findOrFail($invoice);

        $events = $selected->balanceEvents()->getQuery()
            ->where('occurred_at', '>', now()->subDays(InvoiceBalanceEvent::RETENTION_DAYS))
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->simplePaginate(25)
            ->through(static fn (InvoiceBalanceEvent $event): array => [
                'id' => (string) $event->id,
                'outstanding' => $event->outstanding_amount,
                'actor' => $event->actor?->name,
                'occurredAt' => $event->occurred_at->toISOString(),
            ]);

        return Inertia::render('organizations/invoice-balance-history', [
            'organizationId' => (string) $company->id,
            'number' => $selected->invoice_number,
            'events' => $events,
        ]);
    }
}
