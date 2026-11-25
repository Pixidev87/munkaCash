<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\UpdateInvoiceDeadlineRequest;
use App\Services\ReminderDeliveryControls;
use App\Services\ReminderTestQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class InvoiceDeadlineController extends Controller
{
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

        if ($selected->due_date->toDateString() !== $deadline) {
            $updated = $company->invoices()->whereKey($selected->id)->where('revision', $revision)
                ->increment('revision', 1, ['due_date' => $deadline]);

            if ($updated === 0) {
                $this->staleRevision($request);
            }

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
