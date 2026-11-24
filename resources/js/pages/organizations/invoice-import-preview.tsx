import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { confirm, create } from '@/routes/organizations/invoice-imports';
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
    errors: string[];
    status: keyof typeof statusLabels;
};

type Props = {
    organizationId: string;
    importId: string;
    rows: PreviewRow[];
    canImport: boolean;
    summary: { created: number; unchanged: number } | null;
};

const huf = new Intl.NumberFormat('hu-HU', {
    style: 'currency',
    currency: 'HUF',
    maximumFractionDigits: 0,
});

export default function InvoiceImportPreview({
    organizationId,
    importId,
    rows,
    canImport,
    summary,
}: Props) {
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
            </header>

            {summary ? (
                <section
                    className="space-y-3 rounded-xl border p-4"
                    aria-label="Import összesítése"
                >
                    <p>Létrehozva: {summary.created} számla</p>
                    <p>Változatlan: {summary.unchanged} számla</p>
                    <Button asChild>
                        <Link href={index(organizationId)}>
                            Vissza a számlákhoz
                        </Link>
                    </Button>
                </section>
            ) : (
                <>
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
                                    {canImport
                                        ? 'Az új számlák egyenlege az első ellenőrzésig ismeretlen.'
                                        : 'A hibás és ütköző sorok az egész importot blokkolják. Javítsd a fájlt, majd töltsd fel újra.'}
                                </p>
                                <Button
                                    disabled={!canImport || processing}
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

                    <ol
                        className="divide-y rounded-xl border"
                        aria-label="Importálandó sorok"
                    >
                        {rows.map((row) => (
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
                                {row.errors.map((error) => (
                                    <p
                                        className="text-red-600 dark:text-red-400"
                                        key={error}
                                    >
                                        {error}
                                    </p>
                                ))}
                            </li>
                        ))}
                    </ol>
                </>
            )}
        </div>
    );
}
