<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\ExportFilteredInvoicesRequest;
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

        return $export->customers($company);
    }

    public function invoices(Request $request, string $organization, OrganizationCsvExport $export): StreamedResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('exportData', $company);
        abort_unless($company->exportAvailable(), 410, 'A cég exportálási időszaka lejárt.');

        return $export->invoices($company);
    }

    public function filteredInvoices(
        ExportFilteredInvoicesRequest $request,
        InvoiceListQuery $invoiceList,
        OrganizationCsvExport $export,
    ): StreamedResponse {
        $company = $request->company();

        return $export->filteredInvoices($company, $invoiceList->matching($company, $request->criteria()));
    }
}
