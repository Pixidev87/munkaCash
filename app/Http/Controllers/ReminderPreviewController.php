<?php

namespace App\Http\Controllers;

use App\Services\ReminderPreview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderPreviewController extends Controller
{
    public function __invoke(Request $request, string $organization, string $invoice, ReminderPreview $preview): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $selected = $company->invoices()->with('customer')->findOrFail($invoice);

        return Inertia::render('organizations/reminder-preview', [
            'organizationId' => (string) $company->id,
            'customerId' => (string) $selected->customer_id,
            'number' => $selected->invoice_number,
            'dueDate' => $selected->due_date->toDateString(),
            'outstanding' => $selected->outstanding_amount,
            'checkedAt' => $selected->status_checked_at?->toISOString(),
            ...$preview->prepare($company, $selected),
        ]);
    }
}
