<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function store(Request $request, string $organization): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        $data = $request->validateWithBag('customer', [
            'display_name' => ['required', 'string', 'max:255'],
        ], [
            'display_name.required' => 'Add meg az ügyfél nevét.',
            'display_name.max' => 'Az ügyfél neve legfeljebb 255 karakter lehet.',
        ]);

        $customer = $company->customers()->create($data);

        return to_route('organizations.invoices.index', [
            'organization' => $organization,
            'customer' => $customer->id,
        ]);
    }
}
