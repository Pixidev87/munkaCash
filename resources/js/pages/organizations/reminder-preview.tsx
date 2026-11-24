import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/organizations/customers/billing-contact';
import { index, reminderApproval } from '@/routes/organizations/invoices';

type Props = {
    organizationId: string;
    customerId: string;
    invoiceId: string;
    number: string;
    dueDate: string;
    gross: number;
    outstanding: number | null;
    checkedAt: string | null;
    blockers: string[];
    message: { recipient: string; subject: string; body: string } | null;
    canApprove: boolean;
    fingerprint: string | null;
    approvalMinutes: number;
    approval: {
        id: string;
        status: 'active' | 'expired' | 'invalidated';
        approvedBy: string | null;
        approvedAt: string;
        expiresAt: string;
    } | null;
};

const huf = new Intl.NumberFormat('hu-HU', {
    style: 'currency',
    currency: 'HUF',
    maximumFractionDigits: 0,
});
const checkedDate = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

const approvalLabels = {
    active: 'Jóváhagyva',
    expired: 'A jóváhagyás lejárt',
    invalidated: 'A korábbi jóváhagyás érvénytelen',
};

export default function ReminderPreviewPage({
    organizationId,
    customerId,
    invoiceId,
    number,
    dueDate,
    gross,
    outstanding,
    checkedAt,
    blockers,
    message,
    canApprove,
    fingerprint,
    approvalMinutes,
    approval,
}: Props) {
    return (
        <div className="w-full max-w-3xl space-y-6 p-4 sm:p-6">
            <Head title="Emlékeztető előnézete" />
            <header className="space-y-2">
                <Link
                    className="text-sm underline"
                    href={index(organizationId)}
                >
                    Vissza a számlákhoz
                </Link>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Emlékeztető előnézete
                </h1>
                <p className="text-sm text-muted-foreground">
                    A küldés még nem elérhető.
                </p>
            </header>

            <dl className="grid gap-3 rounded-xl border p-4 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">Számlaszám</dt>
                    <dd className="wrap-break-word">{number}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Fizetési határidő</dt>
                    <dd>
                        <time dateTime={dueDate}>{dueDate}</time>
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Bruttó összeg</dt>
                    <dd>{huf.format(gross)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Fennálló összeg</dt>
                    <dd>
                        {outstanding === null
                            ? 'Ismeretlen'
                            : huf.format(outstanding)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        Egyenleg ellenőrizve
                    </dt>
                    <dd>
                        {checkedAt ? (
                            <time dateTime={checkedAt}>
                                {checkedDate.format(new Date(checkedAt))}
                            </time>
                        ) : (
                            'Ellenőrzés szükséges'
                        )}
                    </dd>
                </div>
            </dl>

            <Button asChild variant="outline">
                <Link
                    href={edit({
                        organization: organizationId,
                        customer: customerId,
                    })}
                >
                    Címzett beállítása
                </Link>
            </Button>

            {message ? (
                <section className="space-y-4 rounded-xl border p-4">
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="font-semibold">Címzett</dt>
                            <dd className="wrap-break-word">
                                {message.recipient}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-semibold">Tárgy</dt>
                            <dd>{message.subject}</dd>
                        </div>
                    </dl>
                    <p className="border-t pt-4 text-sm wrap-break-word whitespace-pre-wrap">
                        {message.body}
                    </p>
                </section>
            ) : (
                <section className="space-y-2 rounded-xl border p-4">
                    <h2 className="font-semibold">
                        A levél még nem állítható össze
                    </h2>
                    <ul className="list-disc space-y-1 pl-5 text-sm">
                        {blockers.map((blocker) => (
                            <li key={blocker}>{blocker}</li>
                        ))}
                    </ul>
                </section>
            )}
            {approval && (
                <section
                    className="space-y-2 rounded-xl border p-4"
                    aria-label="Jóváhagyás állapota"
                >
                    <h2 className="font-semibold" role="status">
                        {approvalLabels[approval.status]}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Jóváhagyva{' '}
                        <time dateTime={approval.approvedAt}>
                            {checkedDate.format(new Date(approval.approvedAt))}
                        </time>
                        {approval.approvedBy ? ` · ${approval.approvedBy}` : ''}
                    </p>
                    {approval.status === 'active' && (
                        <p className="text-sm text-muted-foreground">
                            Érvényes eddig{' '}
                            <time dateTime={approval.expiresAt}>
                                {checkedDate.format(
                                    new Date(approval.expiresAt),
                                )}
                            </time>
                        </p>
                    )}
                </section>
            )}

            {fingerprint && canApprove && approval?.status !== 'active' && (
                <Form
                    {...reminderApproval.form({
                        organization: organizationId,
                        invoice: invoiceId,
                    })}
                    className="space-y-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="fingerprint"
                                value={fingerprint}
                            />
                            <input
                                type="hidden"
                                name="previous_approval_id"
                                value={approval?.id ?? ''}
                            />
                            <p className="text-sm text-muted-foreground">
                                A jóváhagyás erre a levéltervezetre szól és{' '}
                                {approvalMinutes} percig érvényes.
                            </p>
                            <Button type="submit" disabled={processing}>
                                Levéltervezet jóváhagyása
                            </Button>
                            <InputError
                                message={
                                    errors.fingerprint ??
                                    errors.previous_approval_id
                                }
                                role="alert"
                            />
                        </>
                    )}
                </Form>
            )}

            {message && !canApprove && (
                <p className="text-sm text-muted-foreground">
                    A levéltervezetet a cég tulajdonosa hagyhatja jóvá.
                </p>
            )}
        </div>
    );
}
