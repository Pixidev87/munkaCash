import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import InvoiceCard, {
    invoiceSourceLabels,
    invoiceStatusLabels,
    type Invoice,
} from '@/components/invoices/invoice-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/organizations';
import { create as createImport } from '@/routes/organizations/invoice-imports';
import { store as storeCustomer } from '@/routes/organizations/customers';
import { index, store as storeInvoice } from '@/routes/organizations/invoices';

type NamedRecord = { id: string; name: string };

type Props = {
    organization: NamedRecord;
    customers: NamedRecord[];
    selectedCustomerId: string;
    filters: {
        invoiceNumber: string;
        status: string;
        source: string;
        customerId: string;
        disputed: boolean;
        overdue: boolean;
        awaitingBalance: boolean;
        billingUnconfirmed: boolean;
        sort: string;
    };
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
    filters,
    invoices,
}: Props) {
    const { organizationReadOnly } = usePage<{
        organizationReadOnly: boolean;
    }>().props;
    const hasFilters = Boolean(
        filters.invoiceNumber ||
        filters.status ||
        filters.source ||
        filters.customerId ||
        filters.disputed ||
        filters.overdue ||
        filters.awaitingBalance ||
        filters.billingUnconfirmed ||
        filters.sort,
    );
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
                {organizationReadOnly ? (
                    <p className="text-sm text-muted-foreground">
                        A cég adatai írásvédettek.
                    </p>
                ) : (
                    <Button asChild variant="outline">
                        <Link href={createImport(organization.id)}>
                            CSV-import
                        </Link>
                    </Button>
                )}
            </header>

            {!organizationReadOnly && (
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
                                        aria-invalid={Boolean(
                                            errors.display_name,
                                        )}
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
                                        A számlához először rögzíts egy
                                        ügyfelet.
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
                                        <InputError
                                            message={errors.customer_id}
                                        />
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
                                            aria-invalid={Boolean(
                                                errors.due_date,
                                            )}
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
                                        <InputError
                                            message={errors.gross_amount}
                                        />
                                    </div>
                                </div>
                                <Button
                                    disabled={
                                        processing || customers.length === 0
                                    }
                                    type="submit"
                                >
                                    Számla mentése
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            )}

            <section className="space-y-3">
                <h2 className="text-lg font-semibold">Rögzített számlák</h2>
                <Form
                    key={JSON.stringify(filters)}
                    {...index.form(organization.id)}
                    errorBag="invoiceFilters"
                    options={{ preserveScroll: true }}
                    className="space-y-3 rounded-xl border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="filter-invoice-number">
                                        Teljes számlaszám
                                    </Label>
                                    <Input
                                        id="filter-invoice-number"
                                        name="invoice_number"
                                        maxLength={100}
                                        defaultValue={filters.invoiceNumber}
                                    />
                                    <InputError
                                        message={errors.invoice_number}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-invoice-status">
                                        Fizetési állapot
                                    </Label>
                                    <select
                                        id="filter-invoice-status"
                                        name="status"
                                        defaultValue={filters.status}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">Összes állapot</option>
                                        {Object.entries(
                                            invoiceStatusLabels,
                                        ).map(([status, label]) => (
                                            <option key={status} value={status}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-invoice-source">
                                        Számla forrása
                                    </Label>
                                    <select
                                        id="filter-invoice-source"
                                        name="source"
                                        defaultValue={filters.source}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">Összes forrás</option>
                                        {Object.entries(
                                            invoiceSourceLabels,
                                        ).map(([source, label]) => (
                                            <option key={source} value={source}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.source} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="filter-invoice-customer">
                                        Számla ügyfele
                                    </Label>
                                    <select
                                        id="filter-invoice-customer"
                                        name="customer_id"
                                        defaultValue={filters.customerId}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">Összes ügyfél</option>
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
                                    <Label htmlFor="filter-invoice-sort">
                                        Rendezés
                                    </Label>
                                    <select
                                        id="filter-invoice-sort"
                                        name="sort"
                                        defaultValue={filters.sort}
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Legutóbb rögzítettek elöl
                                        </option>
                                        <option value="due_asc">
                                            Határidő szerint növekvő
                                        </option>
                                        <option value="due_desc">
                                            Határidő szerint csökkenő
                                        </option>
                                        <option value="outstanding_desc">
                                            Legnagyobb fennálló összeg elöl
                                        </option>
                                    </select>
                                    <InputError message={errors.sort} />
                                </div>
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="disputed"
                                    value="1"
                                    defaultChecked={filters.disputed}
                                />
                                Csak vitatott számlák
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="overdue"
                                    value="1"
                                    defaultChecked={filters.overdue}
                                />
                                Csak lejárt, ellenőrzött, nem vitatott számlák
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="awaiting_balance"
                                    value="1"
                                    defaultChecked={filters.awaitingBalance}
                                />
                                Csak egyenleg-ellenőrzésre váró számlák
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="billing_unconfirmed"
                                    value="1"
                                    defaultChecked={filters.billingUnconfirmed}
                                />
                                Csak megerősített pénzügyi cím nélküli számlák
                            </label>
                            <InputError
                                message={
                                    errors.disputed ??
                                    errors.overdue ??
                                    errors.awaiting_balance ??
                                    errors.billing_unconfirmed ??
                                    errors.page
                                }
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Szűrés
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
                {invoices.data.length ? (
                    <ul className="space-y-3">
                        {invoices.data.map((invoice) => (
                            <InvoiceCard
                                key={invoice.id}
                                invoice={invoice}
                                readOnly={organizationReadOnly}
                                organizationId={organization.id}
                            />
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {hasFilters
                            ? 'Nincs a szűrésnek megfelelő számla.'
                            : 'Ezen az oldalon nincs rögzített számla.'}
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
