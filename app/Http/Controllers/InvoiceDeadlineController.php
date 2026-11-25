<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\UpdateInvoiceDeadlineRequest;
use App\Models\InvoiceDeadlineEvent;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceDeadlineController extends Controller
{
    public function history(Request $request, string $organization, string $invoice): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $selected = $company->invoices()->findOrFail($invoice);
        $events = $selected->deadlineEvents()->getQuery()
            ->where('occurred_at', '>', now()->subDays(InvoiceDeadlineEvent::RETENTION_DAYS))
            ->with('actor:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->simplePaginate(25)
            ->through(static fn (InvoiceDeadlineEvent $event): array => [
                'id' => (string) $event->id,
                'previousDate' => $event->previous_due_date->toDateString(),
                'date' => $event->due_date->toDateString(),
                'actor' => $event->actor?->name,
                'occurredAt' => $event->occurred_at->toISOString(),
            ]);

        return Inertia::render('organizations/invoice-deadline-history', [
            'organizationId' => (string) $company->id,
            'number' => $selected->invoice_number,
            'events' => $events,
        ]);
    }

    public function update(
        UpdateInvoiceDeadlineRequest $request,
        string $organization,
        string $invoice,
        ReminderTestQueue $queue,
    ): RedirectResponse {
        $company = $request->user()->findActiveOrganization($organization);
        $selected = $company->invoices()->findOrFail($invoice);
        $revision = (int) $request->validated('revision');
        $deadline = $request->validated('due_date');

        if ($selected->revision !== $revision) {
            $this->staleRevision($request);
        }

        $previousDate = $selected->due_date->toDateString();
        if ($previousDate !== $deadline) {
            $updated = $company->invoices()->whereKey($selected->id)->where('revision', $revision)
                ->increment('revision', 1, ['due_date' => $deadline]);

            if ($updated === 0) {
                $this->staleRevision($request);
            }

            $selected->deadlineEvents()->create([
                'actor_id' => $request->user()->id,
                'previous_due_date' => $previousDate,
                'due_date' => $deadline,
                'occurred_at' => now(),
            ]);
            $queue->blockPendingForInvoice($selected, ReminderDeliveryControls::INVOICE_DEADLINE_CHANGED);
        }

        return back();
    }

    private function staleRevision(UpdateInvoiceDeadlineRequest $request): never
    {
        throw ValidationException::withMessages([
            'revision' => 'A számla közben változott. Frissítsd a számlalistát, majd próbáld újra.',
        ])->errorBag($request->errorBagName());
    }
}
