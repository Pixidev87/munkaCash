import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/organizations/invoices';

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

type BalanceEvent = {
    id: string;
    outstanding: number | null;
    actor: string | null;
    occurredAt: string;
};

export default function InvoiceBalanceHistory({
    organizationId,
    number,
    events,
}: {
    organizationId: string;
    number: string;
    events: {
        data: BalanceEvent[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Egyenlegtörténet · ${number}`} />
            <Button variant="outline" asChild>
                <Link href={index(organizationId)}>Vissza a számlákhoz</Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Egyenlegtörténet
            </h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {number}
            </p>
            {events.data.length ? (
                <ul className="divide-y rounded-xl border">
                    {events.data.map((event) => (
                        <li key={event.id} className="space-y-1 p-4 text-sm">
                            <p className="font-semibold">
                                {event.outstanding === null
                                    ? 'Egyenleg visszavonva'
                                    : `Ellenőrzött fennálló összeg: ${huf.format(event.outstanding)}`}
                            </p>
                            <time
                                dateTime={event.occurredAt}
                                className="text-muted-foreground"
                            >
                                {timestamp.format(new Date(event.occurredAt))}
                            </time>
                            <p className="wrap-break-word">
                                {event.actor ?? 'Törölt fiók'}
                            </p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Az elmúlt 90 napból nincs rögzített egyenlegművelet ezen az
                    oldalon.
                </p>
            )}
            {(events.prev_page_url || events.next_page_url) && (
                <nav
                    aria-label="Egyenlegtörténet lapozása"
                    className="flex gap-4 text-sm"
                >
                    {events.prev_page_url && (
                        <Link className="underline" href={events.prev_page_url}>
                            Előző oldal
                        </Link>
                    )}
                    {events.next_page_url && (
                        <Link className="underline" href={events.next_page_url}>
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
