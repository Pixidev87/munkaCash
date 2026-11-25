<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\FilterCustomersRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(FilterCustomersRequest $request, string $organization): Response
    {
        $company = $request->user()->findActiveOrganization($organization);
        $filters = $request->safe()->only(['name', 'billing_contact', 'blocked']);
        $name = $filters['name'] ?? '';
        $billingContact = $filters['billing_contact'] ?? '';
        $customers = $company->customers()->getQuery()
            ->when(is_string($name) && $name !== '', function ($query) use ($name) {
                $escaped = strtr($name, ['!' => '!!', '%' => '!%', '_' => '!_']);

                return $query->whereRaw("display_name LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            })
            ->when($billingContact === 'missing', fn ($query) => $query->whereNull('billing_email'))
            ->when($billingContact === 'unconfirmed', fn ($query) => $query->whereNotNull('billing_email')->whereNull('billing_email_confirmed_at'))
            ->when($billingContact === 'confirmed', fn ($query) => $query->whereNotNull('billing_email')->whereNotNull('billing_email_confirmed_at'))
            ->when(isset($filters['blocked']), fn ($query) => $query->where('do_not_contact', true))
            ->orderBy('display_name')
            ->orderBy('id')
            ->simplePaginate(25, ['id', 'display_name', 'billing_email', 'billing_email_confirmed_at', 'do_not_contact'])
            ->appends($filters)
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
            'filters' => [
                'name' => $name,
                'billingContact' => $billingContact,
                'blocked' => isset($filters['blocked']),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request, string $organization): RedirectResponse
    {
        $company = $request->user()->findActiveOrganization($organization);
        $customer = $company->customers()->create($request->safe()->only(['display_name']));

        if ($request->validated('origin') === 'directory') {
            return to_route('organizations.customers.billing-contact.edit', [$company, $customer]);
        }

        return to_route('organizations.invoices.index', [
            'organization' => $organization,
            'customer' => $customer->id,
        ]);
    }
}
