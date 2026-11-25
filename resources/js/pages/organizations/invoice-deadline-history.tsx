import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/organizations/invoices';

const timestamp = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

type DeadlineEvent = {
    id: string;
    previousDate: string;
    date: string;
    actor: string | null;
    occurredAt: string;
};

export default function InvoiceDeadlineHistory({
    organizationId,
    number,
    events,
}: {
    organizationId: string;
    number: string;
    events: {
        data: DeadlineEvent[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Határidő-előzmények · ${number}`} />
            <Button variant="outline" asChild>
                <Link
                    href={index(organizationId, {
                        query: { invoice_number: number },
                    })}
                >
                    Vissza a számlához
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Határidő-előzmények
            </h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {number}
            </p>

            {events.data.length ? (
                <ul className="divide-y rounded-xl border">
                    {events.data.map((event) => (
                        <li key={event.id} className="space-y-1 p-4 text-sm">
                            <p>
                                <time dateTime={event.previousDate}>
                                    {event.previousDate}
                                </time>
                                {' → '}
                                <strong>
                                    <time dateTime={event.date}>
                                        {event.date}
                                    </time>
                                </strong>
                            </p>
                            <p className="wrap-break-word text-muted-foreground">
                                {event.actor ?? 'Törölt fiók'} ·{' '}
                                <time dateTime={event.occurredAt}>
                                    {timestamp.format(
                                        new Date(event.occurredAt),
                                    )}
                                </time>
                            </p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Az elmúlt 90 napból nincs határidőjavítás ezen az oldalon.
                </p>
            )}

            {(events.prev_page_url || events.next_page_url) && (
                <nav
                    aria-label="Határidő-előzmények lapozása"
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
