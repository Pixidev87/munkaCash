import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { reminderDeliverySettings } from '@/routes/organizations';
import { index } from '@/routes/organizations/invoices';

export default function OrganizationPage({
    name,
    organizationId,
    remindersEnabled,
    canManageReminders,
}: {
    name: string;
    organizationId: string;
    remindersEnabled: boolean;
    canManageReminders: boolean;
}) {
    return (
        <div className="flex w-full max-w-3xl flex-col items-start gap-4 p-4 sm:p-6">
            <Head title={name} />
            <Button asChild variant="outline">
                <Link href={dashboard()}>
                    <ArrowLeft aria-hidden="true" />
                    Vissza a cégeimhez
                </Link>
            </Button>
            <h1 className="w-full text-2xl font-semibold tracking-tight wrap-break-word">
                {name}
            </h1>
            <Button asChild>
                <Link href={index(organizationId)}>Számlák megnyitása</Link>
            </Button>
            <section className="w-full space-y-3 rounded-xl border p-4">
                <h2 className="font-semibold">Fizetési emlékeztetők</h2>
                <p className="text-sm text-muted-foreground" role="status">
                    {remindersEnabled
                        ? 'Küldés engedélyezve'
                        : 'Küldés szünetel'}
                </p>
                {canManageReminders && (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Szüneteltetéskor leállnak a cég várakozó
                            tesztküldései.
                        </p>
                        <Form
                            {...reminderDeliverySettings.form(organizationId)}
                            className="space-y-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="enabled"
                                        value={remindersEnabled ? '0' : '1'}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {remindersEnabled
                                            ? 'Küldés szüneteltetése'
                                            : 'Küldés engedélyezése'}
                                    </Button>
                                    <InputError
                                        message={errors.enabled}
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
