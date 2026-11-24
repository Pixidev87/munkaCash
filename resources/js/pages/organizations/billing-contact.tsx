import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    confirm,
    update,
} from '@/routes/organizations/customers/billing-contact';
import { index } from '@/routes/organizations/invoices';

type Props = {
    organizationId: string;
    customer: {
        id: string;
        name: string;
        billingEmail: string | null;
        confirmed: boolean;
        revision: number;
    };
};

export default function BillingContactPage({
    organizationId,
    customer,
}: Props) {
    const routeArgs = { organization: organizationId, customer: customer.id };

    return (
        <div className="w-full max-w-2xl space-y-6 p-4 sm:p-6">
            <Head title="Pénzügyi kapcsolattartó" />
            <header className="space-y-2">
                <Link
                    className="text-sm underline"
                    href={index(organizationId)}
                >
                    Vissza a számlákhoz
                </Link>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Pénzügyi kapcsolattartó
                </h1>
                <p className="wrap-break-word text-muted-foreground">
                    {customer.name}
                </p>
            </header>

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

            {customer.billingEmail && (
                <section className="space-y-3 rounded-xl border p-4">
                    <h2 className="font-semibold">Mentett cím ellenőrzése</h2>
                    <p className="text-sm wrap-break-word" role="status">
                        {customer.billingEmail} ·{' '}
                        {customer.confirmed
                            ? 'Megerősítve'
                            : 'Megerősítésre vár'}
                    </p>
                    {!customer.confirmed && (
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
                </section>
            )}
        </div>
    );
}
