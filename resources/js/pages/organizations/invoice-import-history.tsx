import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { create, show } from '@/routes/organizations/invoice-imports';

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
}: {
    organizationId: string;
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
