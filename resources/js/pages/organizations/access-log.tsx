import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/organizations/members';

const actions = {
    invitation_issued: 'Meghívó kiadása',
    invitation_accepted: 'Meghívó elfogadása',
    invitation_cancelled: 'Meghívó visszavonása',
    member_revoked: 'Munkatársi hozzáférés visszavonása',
};

const timestamp = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

type AccessEvent = {
    id: string;
    action: keyof typeof actions;
    actor: string | null;
    subject: string | null;
    occurredAt: string;
};

type Props = {
    organization: { id: string; name: string };
    events: {
        data: AccessEvent[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function AccessLog({ organization, events }: Props) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Hozzáférési napló · ${organization.name}`} />
            <Button variant="outline" asChild>
                <Link href={index(organization.id)}>
                    Vissza a munkatársakhoz
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Hozzáférési napló
            </h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {organization.name}
            </p>
            {events.data.length > 0 ? (
                <ul className="divide-y rounded-xl border">
                    {events.data.map((event) => (
                        <li className="space-y-1 p-4 text-sm" key={event.id}>
                            <p className="font-semibold">
                                {actions[event.action]}
                            </p>
                            <time
                                dateTime={event.occurredAt}
                                className="text-muted-foreground"
                            >
                                {timestamp.format(new Date(event.occurredAt))}
                            </time>
                            <p className="wrap-break-word">
                                Műveletet végző: {event.actor ?? 'Törölt fiók'}
                            </p>
                            {event.action === 'member_revoked' && (
                                <p className="wrap-break-word">
                                    Érintett munkatárs:{' '}
                                    {event.subject ?? 'Törölt fiók'}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Az elmúlt 90 napban ezen az oldalon nincs naplóbejegyzés.
                </p>
            )}
            {(events.prev_page_url || events.next_page_url) && (
                <nav
                    aria-label="Hozzáférési napló lapozása"
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
