import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, reminderPreview } from '@/routes/organizations/invoices';
import { update } from '@/routes/organizations/invoices/balance';
import { update as updateDispute } from '@/routes/organizations/invoices/dispute';

export const invoiceStatusLabels = {
    unchecked: 'Ellenőrzés szükséges',
    open: 'Nyitott',
    partially_paid: 'Részben fizetett',
    paid: 'Fizetve',
};

export const invoiceSourceLabels = {
    manual: 'Kézi rögzítés',
    csv: 'CSV-import',
};

export type Invoice = {
    id: string;
    number: string;
    customer: string;
    customerId: string;
    dueDate: string;
    gross: number;
    outstanding: number | null;
    status: keyof typeof invoiceStatusLabels;
    source: keyof typeof invoiceSourceLabels | null;
    checkedAt: string | null;
    checker: string | null;
    revision: number;
    isDisputed: boolean;
};

const huf = new Intl.NumberFormat('hu-HU', {
    style: 'currency',
    currency: 'HUF',
    maximumFractionDigits: 0,
});
const checkedDate = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

export default function InvoiceCard({
    invoice,
    organizationId,
    readOnly = false,
}: {
    invoice: Invoice;
    organizationId: string;
    readOnly?: boolean;
}) {
    const amountId = `balance-${invoice.id}`;
    const disputeId = `dispute-${invoice.id}`;

    return (
        <li className="space-y-4 rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="font-semibold wrap-break-word">
                        {invoice.number}
                    </h3>
                    <Link
                        className="text-sm wrap-break-word text-muted-foreground underline"
                        href={index(organizationId, {
                            query: { customer_id: invoice.customerId },
                        })}
                    >
                        {invoice.customer}
                    </Link>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm text-muted-foreground">
                        {invoice.source === null
                            ? 'Ismeretlen forrás'
                            : invoiceSourceLabels[invoice.source]}
                    </p>
                    <p className="rounded-md bg-muted px-2 py-1 text-sm">
                        {invoiceStatusLabels[invoice.status]}
                    </p>
                    {invoice.isDisputed && (
                        <p className="rounded-md border border-amber-500 px-2 py-1 text-sm">
                            Vitatott
                        </p>
                    )}
                </div>
            </div>
            <dl className="grid gap-2 text-sm sm:grid-cols-3">
                <div>
                    <dt className="text-muted-foreground">Határidő</dt>
                    <dd>
                        <time dateTime={invoice.dueDate}>
                            {invoice.dueDate}
                        </time>
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Bruttó összeg</dt>
                    <dd>{huf.format(invoice.gross)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Fennálló összeg</dt>
                    <dd>
                        {invoice.outstanding === null
                            ? 'Ismeretlen'
                            : huf.format(invoice.outstanding)}
                    </dd>
                </div>
            </dl>

            {!readOnly && (
                <Form
                    {...update.form({
                        organization: organizationId,
                        invoice: invoice.id,
                    })}
                    errorBag={amountId}
                    options={{ preserveScroll: true }}
                    className="space-y-1"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="revision"
                                value={invoice.revision}
                            />
                            <Label htmlFor={amountId}>
                                Ellenőrzött fennálló összeg (Ft)
                            </Label>
                            <div className="mt-2 flex flex-wrap gap-2">
                                <Input
                                    id={amountId}
                                    name="outstanding_amount"
                                    type="number"
                                    min={0}
                                    max={invoice.gross}
                                    step={1}
                                    required
                                    defaultValue={invoice.outstanding ?? ''}
                                    aria-invalid={Boolean(
                                        errors.outstanding_amount,
                                    )}
                                    aria-describedby={
                                        errors.outstanding_amount
                                            ? `${amountId}-error`
                                            : undefined
                                    }
                                    className="w-48 max-w-full"
                                />
                                <Button
                                    disabled={processing}
                                    type="submit"
                                    variant="outline"
                                >
                                    Egyenleg megerősítése
                                </Button>
                            </div>
                            <InputError
                                id={`${amountId}-error`}
                                message={
                                    errors.outstanding_amount ?? errors.revision
                                }
                            />
                        </>
                    )}
                </Form>
            )}
            <Button asChild variant="outline">
                <Link
                    href={reminderPreview({
                        organization: organizationId,
                        invoice: invoice.id,
                    })}
                >
                    Emlékeztető előnézete
                </Link>
            </Button>
            {!readOnly && (
                <Form
                    {...updateDispute.form({
                        organization: organizationId,
                        invoice: invoice.id,
                    })}
                    errorBag={disputeId}
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="revision"
                                value={invoice.revision}
                            />
                            <input
                                type="hidden"
                                name="is_disputed"
                                value={invoice.isDisputed ? '0' : '1'}
                            />
                            <Button
                                disabled={processing}
                                aria-describedby={
                                    errors.revision || errors.is_disputed
                                        ? `${disputeId}-error`
                                        : undefined
                                }
                                type="submit"
                                variant="outline"
                            >
                                {invoice.isDisputed
                                    ? 'Vitatottság feloldása'
                                    : 'Megjelölés vitatottként'}
                            </Button>
                            <InputError
                                id={`${disputeId}-error`}
                                message={errors.revision ?? errors.is_disputed}
                            />
                        </>
                    )}
                </Form>
            )}
            {invoice.checkedAt && (
                <p className="text-sm text-muted-foreground">
                    Kézzel ellenőrizve{' '}
                    <time dateTime={invoice.checkedAt}>
                        {checkedDate.format(new Date(invoice.checkedAt))}
                    </time>
                    {invoice.checker ? ` · ${invoice.checker}` : ''}
                </p>
            )}
        </li>
    );
}
