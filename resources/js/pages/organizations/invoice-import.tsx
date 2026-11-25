import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as history,
    store,
    template,
} from '@/routes/organizations/invoice-imports';
import { index } from '@/routes/organizations/invoices';

type Props = {
    organization: { id: string; name: string };
    customers: { id: string; display_name: string }[];
    maxRows: number;
    maxSizeKiB: number;
};

export default function InvoiceImportPage({
    organization,
    customers,
    maxRows,
    maxSizeKiB,
}: Props) {
    const { organizationReadOnly } = usePage<{
        organizationReadOnly: boolean;
    }>().props;
    return (
        <div className="w-full max-w-3xl space-y-6 p-4 sm:p-6">
            <Head title="CSV-import" />
            <header className="space-y-2">
                <Link
                    className="text-sm underline"
                    href={index(organization.id)}
                >
                    Vissza a számlákhoz
                </Link>
                <h1 className="text-2xl font-semibold tracking-tight">
                    CSV-import
                </h1>
                <p className="text-sm text-muted-foreground">
                    {organization.name}
                </p>
                <Link
                    className="text-sm underline"
                    href={history(organization.id)}
                >
                    Importelőzmények
                </Link>
            </header>

            {organizationReadOnly ? (
                <p className="text-sm text-muted-foreground">
                    Írásvédett céghez nem indítható új import.
                </p>
            ) : (
                <Form
                    {...store.form(organization.id)}
                    className="space-y-4 rounded-xl border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                UTF-8, pontosvesszővel tagolt CSV. Legfeljebb{' '}
                                {maxRows} sor és {maxSizeKiB} KiB. A sablon
                                mintasorában cseréld ki a customer_id mezőt a
                                cég egy meglévő ügyfelének azonosítójára.
                            </p>
                            <Button asChild variant="outline">
                                <a
                                    href={template(organization.id).url}
                                    download
                                >
                                    Sablon letöltése
                                </a>
                            </Button>
                            <div className="space-y-2">
                                <Label htmlFor="csv">CSV-fájl</Label>
                                <Input
                                    id="csv"
                                    name="csv"
                                    type="file"
                                    accept=".csv,text/csv"
                                    required
                                    aria-describedby={
                                        errors.csv ? 'csv-error' : undefined
                                    }
                                    aria-invalid={Boolean(errors.csv)}
                                />
                                <InputError
                                    id="csv-error"
                                    message={errors.csv}
                                />
                            </div>
                            <Button disabled={processing} type="submit">
                                Előnézet készítése
                            </Button>
                        </>
                    )}
                </Form>
            )}

            <section className="space-y-2">
                <h2 className="font-semibold">Ügyfél-azonosítók</h2>
                {customers.length ? (
                    <dl className="divide-y rounded-xl border">
                        {customers.map((customer) => (
                            <div
                                key={customer.id}
                                className="flex items-baseline justify-between gap-4 px-4 py-2 text-sm"
                            >
                                <dt className="min-w-0 wrap-break-word">
                                    {customer.display_name}
                                </dt>
                                <dd className="font-mono">{customer.id}</dd>
                            </div>
                        ))}
                    </dl>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Rögzíts egy ügyfelet a Számlák oldalon, hogy legyen
                        használható azonosítód.
                    </p>
                )}
            </section>
        </div>
    );
}
