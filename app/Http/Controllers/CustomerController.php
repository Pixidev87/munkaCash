<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $customers = $company->customers()->getQuery()
            ->orderBy('display_name')
            ->orderBy('id')
            ->simplePaginate(25, ['id', 'display_name', 'billing_email', 'billing_email_confirmed_at', 'do_not_contact'])
            ->through(static fn (Customer $customer): array => [
                'id' => (string) $customer->id,
                'name' => $customer->display_name,
                'billingContact' => match (true) {
                    $customer->billing_email === null => 'missing',
                    $customer->billing_email_confirmed_at === null => 'unconfirmed',
                    default => 'confirmed',
                },
                'doNotContact' => $customer->do_not_contact,
            ]);

        return Inertia::render('organizations/customers', [
            'organization' => ['id' => (string) $company->id, 'name' => $company->name],
            'customers' => $customers,
        ]);
    }

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
