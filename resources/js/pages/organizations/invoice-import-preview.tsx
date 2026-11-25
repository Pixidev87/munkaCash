import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import {
    confirm,
    create,
    destroy,
    errorReport,
} from '@/routes/organizations/invoice-imports';
import { index } from '@/routes/organizations/invoices';

const statusLabels = {
    new: 'Új',
    unchanged: 'Változatlan',
    conflict: 'Ütközés',
    invalid: 'Hibás',
};

type PreviewRow = {
    line: number;
    data: {
        customer_id: string;
        invoice_number: string;
        due_date: string;
        gross_amount: number;
    } | null;
    errors: { code: string; column: string | null; message: string }[];
    status: keyof typeof statusLabels;
};

type RowFilter = 'all' | 'issues' | PreviewRow['status'];

type Props = {
    organizationId: string;
    importId: string;
    expiresAt: string;
    rows: PreviewRow[];
    canImport: boolean;
    previewCounts: Record<keyof typeof statusLabels, number> | null;
    summary: {
        created: number;
        unchanged: number;
        hasTrackedInvoices: boolean;
    } | null;
};

const timestamp = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

const huf = new Intl.NumberFormat('hu-HU', {
    style: 'currency',
    currency: 'HUF',
    maximumFractionDigits: 0,
});

export default function InvoiceImportPreview({
    organizationId,
    importId,
    expiresAt,
    rows,
    canImport,
    previewCounts,
    summary,
}: Props) {
    const { organizationReadOnly } = usePage<{
        organizationReadOnly: boolean;
    }>().props;
    const [rowFilter, setRowFilter] = useState<RowFilter>('all');
    const visibleRows = rows.filter((row) => {
        if (rowFilter === 'issues') {
            return row.status === 'invalid' || row.status === 'conflict';
        }

        return rowFilter === 'all' || row.status === rowFilter;
    });
    const title = summary ? 'Import eredménye' : 'Import előnézete';

    return (
        <div className="w-full max-w-4xl space-y-6 p-4 sm:p-6">
            <Head title={title} />
            <header className="space-y-2">
                <Link
                    className="text-sm underline"
                    href={create(organizationId)}
                >
                    Új CSV feltöltése
                </Link>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {summary
                        ? 'Eredmény elérhető eddig'
                        : 'Előnézet elérhető eddig'}{' '}
                    <time dateTime={expiresAt}>
                        {timestamp.format(new Date(expiresAt))}
                    </time>
                </p>
            </header>

            {summary ? (
                <section
                    className="space-y-3 rounded-xl border p-4"
                    aria-label="Import összesítése"
                >
                    <p>Létrehozva: {summary.created} számla</p>
                    <p>Változatlan: {summary.unchanged} számla</p>
                    <div className="flex flex-wrap gap-2">
                        {summary.created > 0 && !organizationReadOnly && (
                            <Button asChild>
                                <Link
                                    href={index(organizationId, {
                                        query: {
                                            ...(summary.hasTrackedInvoices
                                                ? { import_batch: importId }
                                                : { source: 'csv' }),
                                            awaiting_balance: 1,
                                        },
                                    })}
                                >
                                    {summary.hasTrackedInvoices
                                        ? 'Importált számlák egyenlegének ellenőrzése'
                                        : 'CSV-számlák egyenlegének ellenőrzése'}
                                </Link>
                            </Button>
                        )}
                        {summary.hasTrackedInvoices && (
                            <Button asChild variant="outline">
                                <Link
                                    href={index(organizationId, {
                                        query: { import_batch: importId },
                                    })}
                                >
                                    Ezzel az importtal létrehozott számlák
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={index(organizationId)}>
                                Vissza a számlákhoz
                            </Link>
                        </Button>
                    </div>
                </section>
            ) : (
                <>
                    {previewCounts && (
                        <dl
                            aria-label="Import előnézeti összesítése"
                            className="grid grid-cols-2 gap-3 rounded-xl border p-4 text-sm sm:grid-cols-4"
                        >
                            {Object.entries(statusLabels).map(
                                ([status, label]) => (
                                    <div key={status}>
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd className="font-semibold">
                                            {
                                                previewCounts[
                                                    status as keyof typeof statusLabels
                                                ]
                                            }
                                        </dd>
                                    </div>
                                ),
                            )}
                        </dl>
                    )}
                    {previewCounts &&
                        previewCounts.invalid + previewCounts.conflict > 0 && (
                            <Button asChild variant="outline">
                                <a
                                    href={
                                        errorReport({
                                            organization: organizationId,
                                            import: importId,
                                        }).url
                                    }
                                    download
                                >
                                    Hibajelentés letöltése
                                </a>
                            </Button>
                        )}
                    <Form
                        {...confirm.form({
                            organization: organizationId,
                            import: importId,
                        })}
                        className="space-y-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    {organizationReadOnly
                                        ? 'Az írásvédett céghez az import nem erősíthető meg.'
                                        : canImport
                                          ? 'Az új számlák egyenlege az első ellenőrzésig ismeretlen.'
                                          : 'A hibás és ütköző sorok az egész importot blokkolják. Javítsd a fájlt, majd töltsd fel újra.'}
                                </p>
                                <Button
                                    disabled={
                                        organizationReadOnly ||
                                        !canImport ||
                                        processing
                                    }
                                    type="submit"
                                >
                                    Import megerősítése
                                </Button>
                                <InputError
                                    message={errors.import}
                                    role="alert"
                                />
                            </>
                        )}
                    </Form>

                    {!organizationReadOnly && (
                        <details className="space-y-3 rounded-xl border p-4">
                            <summary className="cursor-pointer font-medium">
                                Függő import elvetése
                            </summary>
                            <Form
                                {...destroy.form({
                                    organization: organizationId,
                                    import: importId,
                                })}
                                errorBag="discardImport"
                                className="space-y-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <label className="flex items-start gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="confirm"
                                                value="1"
                                                required
                                                className="mt-1"
                                            />
                                            Megerősítem a függő import
                                            elvetését.
                                        </label>
                                        <InputError
                                            message={errors.confirm}
                                            role="alert"
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Import elvetése
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </details>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="preview-row-filter">
                            Sorok szűrése
                        </Label>
                        <select
                            id="preview-row-filter"
                            value={rowFilter}
                            onChange={(event) =>
                                setRowFilter(event.target.value as RowFilter)
                            }
                            className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm sm:max-w-xs"
                        >
                            <option value="all">Minden sor</option>
                            <option value="issues">
                                Hibás és ütköző sorok
                            </option>
                            {Object.entries(statusLabels).map(
                                ([status, label]) => (
                                    <option key={status} value={status}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                        <p
                            className="text-sm text-muted-foreground"
                            role="status"
                        >
                            {visibleRows.length === 0
                                ? 'Nincs a szűrőnek megfelelő sor.'
                                : `${visibleRows.length} / ${rows.length} sor látható.`}
                        </p>
                    </div>
                    {visibleRows.length > 0 && (
                        <ol
                            className="divide-y rounded-xl border"
                            aria-label="Importálandó sorok"
                        >
                            {visibleRows.map((row) => (
                                <li
                                    className="space-y-2 p-4 text-sm"
                                    key={row.line}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <span className="font-medium wrap-break-word">
                                            {row.line}. sor
                                            {row.data
                                                ? ` · ${row.data.invoice_number}`
                                                : ''}
                                        </span>
                                        <span className="rounded-md bg-muted px-2 py-1">
                                            {statusLabels[row.status]}
                                        </span>
                                    </div>
                                    {row.data && (
                                        <p className="text-muted-foreground">
                                            Ügyfél: {row.data.customer_id} ·
                                            Határidő: {row.data.due_date} ·{' '}
                                            {huf.format(row.data.gross_amount)}
                                        </p>
                                    )}
                                    {row.errors.length > 0 && (
                                        <ul className="space-y-2">
                                            {row.errors.map((error) => (
                                                <li
                                                    className="space-y-1 text-red-600 dark:text-red-400"
                                                    key={`${error.code}:${error.column}:${error.message}`}
                                                >
                                                    <div className="flex flex-wrap items-baseline gap-x-2">
                                                        {error.column && (
                                                            <code className="font-semibold">
                                                                {error.column}
                                                            </code>
                                                        )}
                                                        <code className="text-xs">
                                                            {error.code}
                                                        </code>
                                                    </div>
                                                    <p>{error.message}</p>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </>
            )}
        </div>
    );
}
