import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/organizations';
import { edit } from '@/routes/organizations/customers/billing-contact';
import { index as invoices } from '@/routes/organizations/invoices';

const billingLabels = {
    missing: 'Hiányzó pénzügyi cím',
    unconfirmed: 'Megerősítésre váró pénzügyi cím',
    confirmed: 'Megerősített pénzügyi cím',
};

type Customer = {
    id: string;
    name: string;
    billingContact: keyof typeof billingLabels;
    doNotContact: boolean;
};

export default function Customers({
    organization,
    customers,
}: {
    organization: { id: string; name: string };
    customers: {
        data: Customer[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Ügyfelek · ${organization.name}`} />
            <Button variant="outline" asChild>
                <Link href={show(organization.id)}>Vissza a céghez</Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">Ügyfelek</h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {organization.name}
            </p>
            {customers.data.length ? (
                <ul className="divide-y rounded-xl border">
                    {customers.data.map((customer) => (
                        <li key={customer.id} className="space-y-3 p-4">
                            <div>
                                <h2 className="font-semibold wrap-break-word">
                                    {customer.name}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {billingLabels[customer.billingContact]}
                                </p>
                                {customer.doNotContact && (
                                    <p className="text-sm">
                                        Emlékeztetők tiltva
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={invoices(organization.id, {
                                            query: { customer_id: customer.id },
                                        })}
                                    >
                                        Ügyfél számlái
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={edit({
                                            organization: organization.id,
                                            customer: customer.id,
                                        })}
                                    >
                                        Pénzügyi kapcsolattartó
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Ezen az oldalon nincs ügyfél.
                </p>
            )}
            {(customers.prev_page_url || customers.next_page_url) && (
                <nav
                    aria-label="Ügyféllista lapozása"
                    className="flex gap-4 text-sm"
                >
                    {customers.prev_page_url && (
                        <Link
                            className="underline"
                            href={customers.prev_page_url}
                        >
                            Előző oldal
                        </Link>
                    )}
                    {customers.next_page_url && (
                        <Link
                            className="underline"
                            href={customers.next_page_url}
                        >
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
