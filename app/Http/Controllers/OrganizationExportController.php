<?php

namespace App\Http\Controllers;

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

        return $export->customers($company);
    }

    public function invoices(Request $request, string $organization, OrganizationCsvExport $export): StreamedResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        Gate::authorize('exportData', $company);

        return $export->invoices($company);
    }
}
