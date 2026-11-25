import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { deliveryLabels, type DeliveryStatus } from '@/lib/reminder-delivery';
import { reminderPreview } from '@/routes/organizations/invoices';

const timestamp = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

type Approval = {
    id: string;
    approvedBy: string | null;
    approvedAt: string;
    expiresAt: string;
    revokedBy: string | null;
    revokedAt: string | null;
    deliveryStatus: DeliveryStatus | null;
};

export default function ReminderApprovalHistory({
    organizationId,
    invoiceId,
    number,
    approvals,
}: {
    organizationId: string;
    invoiceId: string;
    number: string;
    approvals: {
        data: Approval[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Jóváhagyási előzmények · ${number}`} />
            <Button asChild variant="outline">
                <Link
                    href={reminderPreview({
                        organization: organizationId,
                        invoice: invoiceId,
                    })}
                >
                    Vissza a levélelőnézethez
                </Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Jóváhagyási előzmények
            </h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {number}
            </p>

            {approvals.data.length > 0 ? (
                <ul className="divide-y rounded-xl border">
                    {approvals.data.map((approval) => (
                        <li
                            key={approval.id}
                            className="space-y-1 p-4 text-sm wrap-break-word"
                        >
                            <p>
                                <strong>
                                    {approval.approvedBy ?? 'Törölt fiók'}
                                </strong>
                                {' jóváhagyta · '}
                                <time dateTime={approval.approvedAt}>
                                    {timestamp.format(
                                        new Date(approval.approvedAt),
                                    )}
                                </time>
                            </p>
                            <p className="text-muted-foreground">
                                Eredeti lejárat{' '}
                                <time dateTime={approval.expiresAt}>
                                    {timestamp.format(
                                        new Date(approval.expiresAt),
                                    )}
                                </time>
                            </p>
                            {approval.revokedAt && (
                                <p>
                                    {approval.revokedBy ?? 'Törölt fiók'}{' '}
                                    visszavonta ·{' '}
                                    <time dateTime={approval.revokedAt}>
                                        {timestamp.format(
                                            new Date(approval.revokedAt),
                                        )}
                                    </time>
                                </p>
                            )}
                            {approval.deliveryStatus && (
                                <p>{deliveryLabels[approval.deliveryStatus]}</p>
                            )}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Ezen az oldalon nincs jóváhagyás az elmúlt 90 napból.
                </p>
            )}

            {(approvals.prev_page_url || approvals.next_page_url) && (
                <nav
                    aria-label="Jóváhagyási előzmények lapozása"
                    className="flex gap-4 text-sm"
                >
                    {approvals.prev_page_url && (
                        <Link
                            className="underline"
                            href={approvals.prev_page_url}
                        >
                            Előző oldal
                        </Link>
                    )}
                    {approvals.next_page_url && (
                        <Link
                            className="underline"
                            href={approvals.next_page_url}
                        >
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
