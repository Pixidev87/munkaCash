import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import InvoiceCard, { type Invoice } from '@/components/invoices/invoice-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/organizations';
import { store as storeCustomer } from '@/routes/organizations/customers';
import { store as storeInvoice } from '@/routes/organizations/invoices';

type NamedRecord = { id: string; name: string };

type Props = {
    organization: NamedRecord;
    customers: NamedRecord[];
    selectedCustomerId: string;
    invoices: {
        data: Invoice[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function InvoicesPage({
    organization,
    customers,
    selectedCustomerId,
    invoices,
}: Props) {
    return (
        <div className="w-full max-w-5xl space-y-8 p-4 sm:p-6">
            <Head title={`Számlák · ${organization.name}`} />
            <header className="space-y-2">
                <Link
                    className="text-sm underline"
                    href={show(organization.id)}
                >
                    {organization.name}
                </Link>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Számlák
                </h1>
            </header>

            <section className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <Form
                    {...storeCustomer.form(organization.id)}
                    errorBag="customer"
                    resetOnSuccess
                    className="space-y-3 rounded-xl border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <h2 className="font-semibold">Új ügyfél</h2>
                            <div className="space-y-2">
                                <Label htmlFor="customer-name">
                                    Ügyfél neve
                                </Label>
                                <Input
                                    id="customer-name"
                                    name="display_name"
                                    maxLength={255}
                                    required
                                    aria-invalid={Boolean(errors.display_name)}
                                />
                                <InputError message={errors.display_name} />
                            </div>
                            <Button disabled={processing} type="submit">
                                Ügyfél mentése
                            </Button>
                        </>
                    )}
                </Form>

                <Form
                    key={selectedCustomerId}
                    {...storeInvoice.form(organization.id)}
                    errorBag="invoice"
                    resetOnSuccess
                    className="space-y-3 rounded-xl border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <h2 className="font-semibold">Új számla</h2>
                            {customers.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    A számlához először rögzíts egy ügyfelet.
                                </p>
                            )}
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="invoice-customer">
                                        Ügyfél
                                    </Label>
                                    <select
                                        id="invoice-customer"
                                        name="customer_id"
                                        defaultValue={selectedCustomerId}
                                        required
                                        disabled={customers.length === 0}
                                        aria-invalid={Boolean(
                                            errors.customer_id,
                                        )}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Válassz ügyfelet
                                        </option>
                                        {customers.map((customer) => (
                                            <option
                                                key={customer.id}
                                                value={customer.id}
                                            >
                                                {customer.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.customer_id} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="invoice-number">
                                        Számlaszám
                                    </Label>
                                    <Input
                                        id="invoice-number"
                                        name="invoice_number"
                                        maxLength={100}
                                        required
                                        aria-invalid={Boolean(
                                            errors.invoice_number,
                                        )}
                                    />
                                    <InputError
                                        message={errors.invoice_number}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="due-date">
                                        Fizetési határidő
                                    </Label>
                                    <Input
                                        id="due-date"
                                        name="due_date"
                                        type="date"
                                        min="1000-01-01"
                                        max="9999-12-31"
                                        required
                                        aria-invalid={Boolean(errors.due_date)}
                                    />
                                    <InputError message={errors.due_date} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="gross-amount">
                                        Bruttó összeg (Ft)
                                    </Label>
                                    <Input
                                        id="gross-amount"
                                        name="gross_amount"
                                        type="number"
                                        min={1}
                                        step={1}
                                        required
                                        aria-invalid={Boolean(
                                            errors.gross_amount,
                                        )}
                                    />
                                    <InputError message={errors.gross_amount} />
                                </div>
                            </div>
                            <Button
                                disabled={processing || customers.length === 0}
                                type="submit"
                            >
                                Számla mentése
                            </Button>
                        </>
                    )}
                </Form>
            </section>

            <section className="space-y-3">
                <h2 className="text-lg font-semibold">Rögzített számlák</h2>
                {invoices.data.length ? (
                    <ul className="space-y-3">
                        {invoices.data.map((invoice) => (
                            <InvoiceCard
                                key={invoice.id}
                                invoice={invoice}
                                organizationId={organization.id}
                            />
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Ezen az oldalon nincs rögzített számla.
                    </p>
                )}
                {(invoices.prev_page_url || invoices.next_page_url) && (
                    <nav
                        aria-label="Számlák lapozása"
                        className="flex gap-4 text-sm"
                    >
                        {invoices.prev_page_url && (
                            <Link
                                className="underline"
                                href={invoices.prev_page_url}
                            >
                                Előző oldal
                            </Link>
                        )}
                        {invoices.next_page_url && (
                            <Link
                                className="underline"
                                href={invoices.next_page_url}
                            >
                                Következő oldal
                            </Link>
                        )}
                    </nav>
                )}
            </section>
        </div>
    );
}
