import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    contactRestriction,
    index as customerList,
} from '@/routes/organizations/customers';
import {
    confirm,
    revoke,
    update,
} from '@/routes/organizations/customers/billing-contact';
import { index } from '@/routes/organizations/invoices';

type Props = {
    organizationId: string;
    canManageReminders: boolean;
    customer: {
        id: string;
        name: string;
        billingEmail: string | null;
        confirmed: boolean;
        revision: number;
        doNotContact: boolean;
    };
};

export default function BillingContactPage({
    organizationId,
    customer,
    canManageReminders,
}: Props) {
    const { organizationReadOnly } = usePage<{
        organizationReadOnly: boolean;
    }>().props;
    const routeArgs = { organization: organizationId, customer: customer.id };

    return (
        <div className="w-full max-w-2xl space-y-6 p-4 sm:p-6">
            <Head title="Pénzügyi kapcsolattartó" />
            <header className="space-y-2">
                <div className="flex flex-wrap gap-3">
                    <Link
                        className="text-sm underline"
                        href={index(organizationId)}
                    >
                        Vissza a számlákhoz
                    </Link>
                    <Link
                        className="text-sm underline"
                        href={customerList(organizationId)}
                    >
                        Ügyféllista
                    </Link>
                </div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Pénzügyi kapcsolattartó
                </h1>
                <p className="wrap-break-word text-muted-foreground">
                    {customer.name}
                </p>
            </header>

            {organizationReadOnly ? (
                <p className="text-sm text-muted-foreground">
                    A cég adatai írásvédettek.
                </p>
            ) : (
                <Form
                    {...update.form(routeArgs)}
                    errorBag="address"
                    className="space-y-3 rounded-xl border p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="revision"
                                value={customer.revision}
                            />
                            <div className="space-y-2">
                                <Label htmlFor="billing-email">
                                    Pénzügyi e-mail-cím
                                </Label>
                                <Input
                                    key={customer.billingEmail ?? ''}
                                    id="billing-email"
                                    name="billing_email"
                                    type="email"
                                    maxLength={254}
                                    defaultValue={customer.billingEmail ?? ''}
                                    aria-invalid={Boolean(errors.billing_email)}
                                    aria-describedby="billing-email-help"
                                />
                                <p
                                    id="billing-email-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    Címváltozás után új megerősítés szükséges.
                                    Üresen mentve eltávolítod a címet.
                                </p>
                                <InputError
                                    message={
                                        errors.billing_email ?? errors.revision
                                    }
                                    role="alert"
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                Cím mentése
                            </Button>
                        </>
                    )}
                </Form>
            )}

            {customer.billingEmail && (
                <section className="space-y-3 rounded-xl border p-4">
                    <h2 className="font-semibold">Mentett cím ellenőrzése</h2>
                    <p className="text-sm wrap-break-word" role="status">
                        {customer.billingEmail} ·{' '}
                        {customer.confirmed
                            ? 'Megerősítve'
                            : 'Megerősítésre vár'}
                    </p>
                    {!organizationReadOnly && !customer.confirmed && (
                        <Form
                            {...confirm.form(routeArgs)}
                            errorBag="confirmation"
                            className="space-y-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="revision"
                                        value={customer.revision}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Megerősítem a mentett címet
                                    </Button>
                                    <InputError
                                        message={
                                            errors.billing_email ??
                                            errors.revision
                                        }
                                        role="alert"
                                    />
                                </>
                            )}
                        </Form>
                    )}
                    {!organizationReadOnly && customer.confirmed && (
                        <Form
                            {...revoke.form(routeArgs)}
                            errorBag="revocation"
                            className="space-y-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="revision"
                                        value={customer.revision}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Megerősítés visszavonása
                                    </Button>
                                    <InputError
                                        message={errors.revision}
                                        role="alert"
                                    />
                                </>
                            )}
                        </Form>
                    )}
                </section>
            )}
            <section className="space-y-3 rounded-xl border p-4">
                <h2 className="font-semibold">Ügyfél emlékeztetői</h2>
                <p className="text-sm text-muted-foreground" role="status">
                    {customer.doNotContact
                        ? 'Emlékeztetők tiltva'
                        : 'Nincs ügyfélszintű tiltás'}
                </p>
                {canManageReminders && (
                    <>
                        <p className="text-sm text-muted-foreground">
                            A tiltás leállítja az ügyfél várakozó
                            tesztküldéseit, és címcsere után is érvényben marad.
                        </p>
                        <Form
                            {...contactRestriction.form(routeArgs)}
                            className="space-y-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="do_not_contact"
                                        value={
                                            customer.doNotContact ? '0' : '1'
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {customer.doNotContact
                                            ? 'Tiltás feloldása'
                                            : 'Emlékeztetők tiltása'}
                                    </Button>
                                    <InputError
                                        message={errors.do_not_contact}
                                        role="alert"
                                    />
                                </>
                            )}
                        </Form>
                    </>
                )}
            </section>
        </div>
    );
}
