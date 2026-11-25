<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationAccessAction;
use App\Http\Requests\Invoices\ExportFilteredInvoicesRequest;
use App\Models\Organization;
use App\Models\OrganizationAccessEvent;
use App\Services\InvoiceListQuery;
use App\Services\OrganizationCsvExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationExportController extends Controller
{
    public function customers(Request $request, string $organization, OrganizationCsvExport $export): StreamedResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('exportData', $company);
        abort_unless($company->exportAvailable(), 410, 'A cég exportálási időszaka lejárt.');

        $this->recordRequest($request, $company, OrganizationAccessAction::CustomerExport);

        return $export->customers($company);
    }

    public function invoices(Request $request, string $organization, OrganizationCsvExport $export): StreamedResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('exportData', $company);
        abort_unless($company->exportAvailable(), 410, 'A cég exportálási időszaka lejárt.');

        $this->recordRequest($request, $company, OrganizationAccessAction::InvoiceExport);

        return $export->invoices($company);
    }

    public function filteredInvoices(
        ExportFilteredInvoicesRequest $request,
        InvoiceListQuery $invoiceList,
        OrganizationCsvExport $export,
    ): StreamedResponse {
        $company = $request->company();

        $this->recordRequest($request, $company, OrganizationAccessAction::FilteredInvoiceExport);

        return $export->filteredInvoices($company, $invoiceList->matching($company, $request->criteriaWithoutSort()));
    }

    private function recordRequest(Request $request, Organization $company, OrganizationAccessAction $action): void
    {
        if ($request->isMethod('GET')) {
            OrganizationAccessEvent::record($company, $request->user(), $action);
        }
    }
}
