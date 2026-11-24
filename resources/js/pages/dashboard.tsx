import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { dashboard } from '@/routes';
import { show } from '@/routes/organizations';

type Organization = {
    id: string;
    name: string;
};

export default function Dashboard({
    organizations,
}: {
    organizations: Organization[];
}) {
    return (
        <div className="flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6">
            <Head title="Cégeim" />
            <header>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Cégeim
                </h1>
                {organizations.length > 0 && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        Válaszd ki, melyik céggel szeretnél dolgozni.
                    </p>
                )}
            </header>

            {organizations.length > 0 ? (
                <ul className="divide-y overflow-hidden rounded-xl border">
                    {organizations.map((organization) => (
                        <li key={organization.id}>
                            <Link
                                href={show(organization.id)}
                                className="flex min-h-12 items-center justify-between gap-4 px-4 py-3 font-medium hover:bg-muted focus-visible:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                            >
                                <span className="min-w-0 wrap-break-word">
                                    {organization.name}
                                </span>
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                    Jelenleg nincs aktív céges tagságod. Ha hozzáférésre van
                    szükséged, keresd a cég tulajdonosát.
                </p>
            )}
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Cégeim', href: dashboard() }],
};
