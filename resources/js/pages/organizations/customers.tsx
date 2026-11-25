import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/organizations';
import { index, store } from '@/routes/organizations/customers';
import { edit } from '@/routes/organizations/customers/billing-contact';
import { index as invoices } from '@/routes/organizations/invoices';

const billingLabels = {
    missing: 'Hiányzó pénzügyi cím',
    unconfirmed: 'Megerősítésre váró pénzügyi cím',
    confirmed: 'Megerősített pénzügyi cím',
};

type Customer = {
    id: string;
    name: string;
    billingContact: keyof typeof billingLabels;
    doNotContact: boolean;
};

export default function Customers({
    organization,
    customers,
    filters,
}: {
    organization: { id: string; name: string };
    filters: { name: string; billingContact: string; blocked: boolean };
    customers: {
        data: Customer[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const { organizationReadOnly } = usePage<{
        organizationReadOnly: boolean;
    }>().props;
    const hasFilters =
        filters.name || filters.billingContact || filters.blocked;

    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Ügyfelek · ${organization.name}`} />
            <Button variant="outline" asChild>
                <Link href={show(organization.id)}>Vissza a céghez</Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">Ügyfelek</h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {organization.name}
            </p>
            {!organizationReadOnly && (
                <details className="space-y-3 rounded-xl border p-4">
                    <summary className="cursor-pointer font-semibold">
                        Új ügyfél
                    </summary>
                    <Form
                        {...store.form(organization.id)}
                        errorBag="customer"
                        className="space-y-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="origin"
                                    value="directory"
                                />
                                <Label htmlFor="new-customer-name">
                                    Ügyfél neve
                                </Label>
                                <Input
                                    id="new-customer-name"
                                    name="display_name"
                                    maxLength={255}
                                    required
                                />
                                <InputError
                                    message={
                                        errors.display_name ?? errors.origin
                                    }
                                    role="alert"
                                />
                                <Button type="submit" disabled={processing}>
                                    Ügyfél mentése
                                </Button>
                            </>
                        )}
                    </Form>
                </details>
            )}
            <Form
                key={JSON.stringify(filters)}
                {...index.form(organization.id)}
                errorBag="customerFilters"
                className="space-y-3 rounded-xl border p-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="customer-search">
                                Keresés ügyfélnév alapján
                            </Label>
                            <Input
                                id="customer-search"
                                name="name"
                                maxLength={255}
                                defaultValue={filters.name}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="billing-contact-filter">
                                Pénzügyi cím állapota
                            </Label>
                            <select
                                id="billing-contact-filter"
                                name="billing_contact"
                                defaultValue={filters.billingContact}
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Összes állapot</option>
                                {Object.entries(billingLabels).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError message={errors.billing_contact} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="blocked"
                                value="1"
                                defaultChecked={filters.blocked}
                            />
                            Csak letiltott emlékeztetőjű ügyfelek
                        </label>
                        <InputError message={errors.blocked} />
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                Keresés
                            </Button>
                            {hasFilters && (
                                <Link
                                    className="text-sm underline"
                                    href={index(organization.id)}
                                >
                                    Szűrők törlése
                                </Link>
                            )}
                        </div>
                    </>
                )}
            </Form>
            {customers.data.length ? (
                <ul className="divide-y rounded-xl border">
                    {customers.data.map((customer) => (
                        <li key={customer.id} className="space-y-3 p-4">
                            <div>
                                <h2 className="font-semibold wrap-break-word">
                                    {customer.name}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {billingLabels[customer.billingContact]}
                                </p>
                                {customer.doNotContact && (
                                    <p className="text-sm">
                                        Emlékeztetők tiltva
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={invoices(organization.id, {
                                            query: { customer_id: customer.id },
                                        })}
                                    >
                                        Ügyfél számlái
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={edit({
                                            organization: organization.id,
                                            customer: customer.id,
                                        })}
                                    >
                                        Pénzügyi kapcsolattartó
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {hasFilters
                        ? 'Nincs a keresésnek megfelelő ügyfél.'
                        : 'Ezen az oldalon nincs ügyfél.'}
                </p>
            )}
            {(customers.prev_page_url || customers.next_page_url) && (
                <nav
                    aria-label="Ügyféllista lapozása"
                    className="flex gap-4 text-sm"
                >
                    {customers.prev_page_url && (
                        <Link
                            className="underline"
                            href={customers.prev_page_url}
                        >
                            Előző oldal
                        </Link>
                    )}
                    {customers.next_page_url && (
                        <Link
                            className="underline"
                            href={customers.next_page_url}
                        >
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
