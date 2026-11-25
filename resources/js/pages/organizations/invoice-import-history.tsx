import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/organizations/invoice-imports';

const localDate = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

type Import = {
    id: string;
    createdAt: string | null;
    expiresAt: string;
    created: number | null;
    unchanged: number | null;
};

export default function InvoiceImportHistory({
    organizationId,
    imports,
    status,
    counts,
    uploadDates,
}: {
    organizationId: string;
    status: 'pending' | 'completed' | null;
    uploadDates: { from: string | null; to: string | null };
    counts: { pending: number; completed: number };
    imports: {
        data: Import[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title="Importelőzmények" />
            <Button asChild variant="outline">
                <Link href={create(organizationId)}>
                    Vissza a CSV-importhoz
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Importelőzmények
            </h1>
            <Form
                {...index.form(organizationId)}
                key={JSON.stringify([status, uploadDates])}
                className="space-y-3 rounded-xl border p-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="import-status">
                                Import állapota
                            </Label>
                            <select
                                id="import-status"
                                name="status"
                                defaultValue={status ?? ''}
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">
                                    Minden állapot (
                                    {counts.pending + counts.completed})
                                </option>
                                <option value="pending">
                                    Megerősítésre vár ({counts.pending})
                                </option>
                                <option value="completed">
                                    Lezárt import ({counts.completed})
                                </option>
                            </select>
                            <InputError
                                message={errors.status ?? errors.page}
                            />
                        </div>
                        <fieldset className="grid gap-3 sm:grid-cols-2">
                            <legend className="mb-2 text-sm font-medium">
                                Feltöltés dátuma (budapesti idő)
                            </legend>
                            <div className="space-y-2">
                                <Label htmlFor="upload-from">
                                    Ettől a naptól
                                </Label>
                                <Input
                                    id="upload-from"
                                    name="uploaded_from"
                                    type="date"
                                    defaultValue={uploadDates.from ?? ''}
                                />
                                <InputError message={errors.uploaded_from} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="upload-to">Eddig a napig</Label>
                                <Input
                                    id="upload-to"
                                    name="uploaded_to"
                                    type="date"
                                    defaultValue={uploadDates.to ?? ''}
                                />
                                <InputError message={errors.uploaded_to} />
                            </div>
                        </fieldset>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={processing}>
                                Szűrés
                            </Button>
                            {(status || uploadDates.from || uploadDates.to) && (
                                <Button asChild variant="outline">
                                    <Link href={index(organizationId)}>
                                        Szűrők törlése
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
            {imports.data.length ? (
                <ul className="divide-y rounded-xl border">
                    {imports.data.map((batch) => (
                        <li key={batch.id} className="space-y-2 p-4 text-sm">
                            <p className="font-semibold">
                                {batch.created === null
                                    ? 'Megerősítésre vár'
                                    : 'Lezárt import'}
                            </p>
                            {batch.createdAt && (
                                <time dateTime={batch.createdAt}>
                                    {localDate.format(
                                        new Date(batch.createdAt),
                                    )}
                                </time>
                            )}
                            {batch.created !== null && (
                                <p>
                                    Létrehozva: {batch.created} · Változatlan:{' '}
                                    {batch.unchanged ?? 0}
                                </p>
                            )}
                            <p className="text-muted-foreground">
                                Elérhető eddig:{' '}
                                <time dateTime={batch.expiresAt}>
                                    {localDate.format(
                                        new Date(batch.expiresAt),
                                    )}
                                </time>
                            </p>
                            <Link
                                className="inline-block underline"
                                href={show({
                                    organization: organizationId,
                                    import: batch.id,
                                })}
                            >
                                {batch.created === null
                                    ? 'Előnézet megnyitása'
                                    : 'Eredmény megnyitása'}
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Nincs elérhető import ezen az oldalon.
                </p>
            )}
            {(imports.prev_page_url || imports.next_page_url) && (
                <nav
                    aria-label="Importelőzmények lapozása"
                    className="flex gap-4 text-sm"
                >
                    {imports.prev_page_url && (
                        <Link
                            className="underline"
                            href={imports.prev_page_url}
                        >
                            Előző oldal
                        </Link>
                    )}
                    {imports.next_page_url && (
                        <Link
                            className="underline"
                            href={imports.next_page_url}
                        >
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
