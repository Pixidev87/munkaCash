import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { accessLog, show } from '@/routes/organizations';
import { Button } from '@/components/ui/button';

const actions = {
    invitation_issued: 'Meghívó kiadása',
    invitation_accepted: 'Meghívó elfogadása',
    invitation_cancelled: 'Meghívó visszavonása',
    customers_export: 'Ügyfelek CSV-exportjának kérése',
    invoices_export: 'Teljes számlaexport kérése',
    reminder_delivery_export: 'Tesztküldési napló exportjának kérése',
    filtered_invoices_export: 'Szűrt számlaexport kérése',
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
    actors: { id: string; name: string }[];
    filters: {
        action: keyof typeof actions | null;
        from: string | null;
        to: string | null;
        actorId: string | null;
    };
    events: {
        data: AccessEvent[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export default function AccessLog({
    organization,
    actors,
    filters,
    events,
}: Props) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Hozzáférési napló · ${organization.name}`} />
            <Button variant="outline" asChild>
                <Link href={show(organization.id)}>
                    Vissza a cég áttekintéséhez
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Hozzáférési napló
            </h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {organization.name}
            </p>
            <Form
                {...accessLog.form(organization.id)}
                key={JSON.stringify(filters)}
                className="space-y-3 rounded-xl border p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="access-action">
                                Művelet típusa
                            </Label>
                            <select
                                id="access-action"
                                name="action"
                                defaultValue={filters.action ?? ''}
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Minden művelet</option>
                                {Object.entries(actions).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <InputError
                                message={errors.action ?? errors.page}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="access-actor">Végrehajtó</Label>
                            <select
                                id="access-actor"
                                name="actor_id"
                                defaultValue={filters.actorId ?? ''}
                                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Minden végrehajtó</option>
                                {filters.actorId &&
                                    !actors.some(
                                        (actor) => actor.id === filters.actorId,
                                    ) && (
                                        <option value={filters.actorId}>
                                            A kiválasztott fiókhoz nincs
                                            elérhető bejegyzés
                                        </option>
                                    )}
                                {actors.map((actor) => (
                                    <option key={actor.id} value={actor.id}>
                                        {actor.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.actor_id} />
                        </div>
                        <fieldset className="grid gap-3 sm:grid-cols-2">
                            <legend className="mb-2 text-sm font-medium">
                                Időszak (budapesti idő)
                            </legend>
                            <div className="space-y-2">
                                <Label htmlFor="access-from">
                                    Ettől a naptól
                                </Label>
                                <Input
                                    id="access-from"
                                    name="from"
                                    type="date"
                                    defaultValue={filters.from ?? ''}
                                />
                                <InputError message={errors.from} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="access-to">Eddig a napig</Label>
                                <Input
                                    id="access-to"
                                    name="to"
                                    type="date"
                                    defaultValue={filters.to ?? ''}
                                />
                                <InputError message={errors.to} />
                            </div>
                        </fieldset>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={processing}>
                                Szűrés
                            </Button>
                            {(filters.action ||
                                filters.from ||
                                filters.to ||
                                filters.actorId) && (
                                <Button asChild variant="outline">
                                    <Link href={accessLog(organization.id)}>
                                        Szűrők törlése
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
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
