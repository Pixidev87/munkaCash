import { Form, Head, Link, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/organizations/customers/billing-contact';
import {
    index,
    reminderApproval,
    reminderTestDelivery,
} from '@/routes/organizations/invoices';

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
    testDeliveryEnabled: boolean;
    deliveryBlocker: 'organization_paused' | 'customer_do_not_contact' | null;
    testRecipient: boolean;
    testDelivery: {
        status: keyof typeof deliveryLabels;
        reason: string | null;
    } | null;
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

const deliveryLabels = {
    pending: 'Tesztlevél sorban áll',
    sending: 'Tesztküldés folyamatban',
    accepted: 'A Mailpit átvette a tesztlevelet',
    blocked: 'Tesztküldés letiltva',
    unknown: 'A tesztlevél átvétele bizonytalan',
};

const deliveryReasons: Record<string, string> = {
    organization_paused: 'A cég emlékeztetői szünetelnek.',
    customer_do_not_contact: 'Az ügyfél emlékeztetői tiltva vannak.',
    missing_record: 'A számla vagy a jóváhagyás már nem érhető el.',
    approval_expired:
        'A jóváhagyás lejárt, mielőtt a feldolgozó elindult volna.',
    approval_changed: 'A levéltervezet vagy a jóváhagyás közben megváltozott.',
    test_address_required: 'A tesztküldéshez .test végződésű cím szükséges.',
    transport_unconfirmed: 'Ellenőrizd a Mailpit beérkező leveleit.',
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
    testDeliveryEnabled,
    deliveryBlocker,
    testRecipient,
    testDelivery,
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
                {testDeliveryEnabled && (
                    <p className="text-sm text-muted-foreground">
                        A tesztküldés a fejlesztői Mailpit postafiókba érkezik.
                    </p>
                )}
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

            {testDelivery && (
                <section
                    className="space-y-2 rounded-xl border p-4"
                    aria-label="Tesztküldés állapota"
                >
                    <h2 className="font-semibold">
                        {deliveryLabels[testDelivery.status]}
                    </h2>
                    {testDelivery.reason && (
                        <p className="text-sm text-muted-foreground">
                            {deliveryReasons[testDelivery.reason]}
                        </p>
                    )}
                    {(testDelivery.status === 'pending' ||
                        testDelivery.status === 'sending') && (
                        <Button
                            variant="outline"
                            onClick={() => router.reload()}
                        >
                            Állapot frissítése
                        </Button>
                    )}
                </section>
            )}

            {deliveryBlocker && (
                <p className="text-sm text-muted-foreground">
                    {deliveryReasons[deliveryBlocker]}
                </p>
            )}

            {testDeliveryEnabled &&
                !deliveryBlocker &&
                canApprove &&
                approval?.status === 'active' &&
                !testDelivery &&
                (testRecipient ? (
                    <Form
                        {...reminderTestDelivery.form({
                            organization: organizationId,
                            invoice: invoiceId,
                        })}
                        className="space-y-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="approval_id"
                                    value={approval.id}
                                />
                                <Button type="submit" disabled={processing}>
                                    Tesztlevél sorba állítása
                                </Button>
                                <InputError
                                    message={
                                        errors.delivery ?? errors.approval_id
                                    }
                                    role="alert"
                                />
                            </>
                        )}
                    </Form>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        A tesztküldéshez .test végződésű pénzügyi e-mail-címet
                        erősíts meg.
                    </p>
                ))}

            {message && !canApprove && (
                <p className="text-sm text-muted-foreground">
                    A levéltervezetet a cég tulajdonosa hagyhatja jóvá.
                </p>
            )}
        </div>
    );
}
