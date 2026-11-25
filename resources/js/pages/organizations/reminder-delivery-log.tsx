import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    deliveryLabels,
    deliveryReasons,
    type DeliveryStatus,
} from '@/lib/reminder-delivery';
import { show } from '@/routes/organizations';
import { reminderPreview } from '@/routes/organizations/invoices';
import { index } from '@/routes/organizations/reminder-deliveries';

type Delivery = {
    id: string;
    invoiceId: string;
    invoiceNumber: string;
    status: DeliveryStatus;
    reason: string | null;
    availableAt: string | null;
    createdAt: string;
    updatedAt: string;
};

type Props = {
    organization: { id: string; name: string };
    filters: {
        status: DeliveryStatus | null;
        invoiceNumber: string | null;
        reason: string | null;
    };
    deliveries: {
        data: Delivery[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

const formattedDate = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

export default function ReminderDeliveryLog({
    organization,
    filters,
    deliveries,
}: Props) {
    const filtered =
        filters.status !== null ||
        filters.invoiceNumber !== null ||
        filters.reason !== null;

    return (
        <div className="w-full max-w-4xl space-y-6 p-4 sm:p-6">
            <Head title="Tesztküldési napló" />
            <header className="space-y-2">
                <Link
                    className="text-sm underline"
                    href={show(organization.id)}
                >
                    {organization.name}
                </Link>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Tesztküldési napló
                </h1>
            </header>

            <Form
                {...index.form(organization.id)}
                key={JSON.stringify(filters)}
                className="space-y-3 rounded-xl border p-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="delivery-status">
                                    Küldési állapot
                                </Label>
                                <select
                                    id="delivery-status"
                                    name="status"
                                    defaultValue={filters.status ?? ''}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">Minden állapot</option>
                                    {Object.entries(deliveryLabels).map(
                                        ([status, label]) => (
                                            <option key={status} value={status}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError message={errors.status} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="filter-invoice-number">
                                    Pontos számlaszám
                                </Label>
                                <Input
                                    id="filter-invoice-number"
                                    name="invoice_number"
                                    defaultValue={filters.invoiceNumber ?? ''}
                                    maxLength={100}
                                />
                                <InputError message={errors.invoice_number} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="delivery-reason">
                                    Állapot oka
                                </Label>
                                <select
                                    id="delivery-reason"
                                    name="reason"
                                    defaultValue={filters.reason ?? ''}
                                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">Minden ok</option>
                                    {filters.reason &&
                                        !Object.hasOwn(
                                            deliveryReasons,
                                            filters.reason,
                                        ) && (
                                            <option value={filters.reason}>
                                                Egyéb ok
                                            </option>
                                        )}
                                    {Object.entries(deliveryReasons).map(
                                        ([reason, label]) => (
                                            <option key={reason} value={reason}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError message={errors.reason} />
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button disabled={processing} type="submit">
                                Szűrés
                            </Button>
                            {filtered && (
                                <Button asChild variant="outline">
                                    <Link href={index(organization.id)}>
                                        Szűrők törlése
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>

            {deliveries.data.length ? (
                <ul
                    className="divide-y rounded-xl border"
                    aria-label="Tesztküldési kísérletek"
                >
                    {deliveries.data.map((delivery) => (
                        <li className="space-y-2 p-4" key={delivery.id}>
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <Link
                                    href={reminderPreview({
                                        organization: organization.id,
                                        invoice: delivery.invoiceId,
                                    })}
                                    className="min-w-0 font-semibold wrap-break-word underline"
                                >
                                    {delivery.invoiceNumber}
                                </Link>
                                <span className="rounded-md bg-muted px-2 py-1 text-sm">
                                    {deliveryLabels[delivery.status]}
                                </span>
                            </div>
                            {delivery.reason && (
                                <p className="text-sm text-muted-foreground">
                                    {deliveryReasons[delivery.reason] ??
                                        'Egyéb küldési ok.'}
                                </p>
                            )}
                            <dl className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                                <div>
                                    <dt>Sorba állítva</dt>
                                    <dd>
                                        <time dateTime={delivery.createdAt}>
                                            {formattedDate.format(
                                                new Date(delivery.createdAt),
                                            )}
                                        </time>
                                    </dd>
                                </div>
                                {delivery.availableAt && (
                                    <div>
                                        <dt>Legkorábbi feldolgozás</dt>
                                        <dd>
                                            <time
                                                dateTime={delivery.availableAt}
                                            >
                                                {formattedDate.format(
                                                    new Date(
                                                        delivery.availableAt,
                                                    ),
                                                )}
                                            </time>
                                        </dd>
                                    </div>
                                )}
                                <div>
                                    <dt>Utoljára frissítve</dt>
                                    <dd>
                                        <time dateTime={delivery.updatedAt}>
                                            {formattedDate.format(
                                                new Date(delivery.updatedAt),
                                            )}
                                        </time>
                                    </dd>
                                </div>
                            </dl>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {filtered
                        ? 'Nincs a szűrőknek megfelelő tesztküldés.'
                        : 'Ezen az oldalon nincs tesztküldés.'}
                </p>
            )}

            {(deliveries.prev_page_url || deliveries.next_page_url) && (
                <nav
                    aria-label="Tesztküldési napló lapozása"
                    className="flex gap-4 text-sm"
                >
                    {deliveries.prev_page_url && (
                        <Link
                            className="underline"
                            href={deliveries.prev_page_url}
                        >
                            Előző oldal
                        </Link>
                    )}
                    {deliveries.next_page_url && (
                        <Link
                            className="underline"
                            href={deliveries.next_page_url}
                        >
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
