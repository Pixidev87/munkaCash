import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    closeout as startCloseout,
    reminderDeliverySettings,
} from '@/routes/organizations';
import { index as customerList } from '@/routes/organizations/customers';
import { customers, invoices } from '@/routes/organizations/exports';
import { index } from '@/routes/organizations/invoices';
import { index as members } from '@/routes/organizations/members';
import { index as deliveryLog } from '@/routes/organizations/reminder-deliveries';

const localDate = new Intl.DateTimeFormat('hu-HU', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/Budapest',
});

const huf = new Intl.NumberFormat('hu-HU', {
    style: 'currency',
    currency: 'HUF',
    maximumFractionDigits: 0,
});

export default function OrganizationPage({
    name,
    organizationId,
    balanceSummary,
    remindersEnabled,
    canManageReminders,
    canManageMembers,
    canExportData,
    closeout,
    canStartCloseout,
}: {
    name: string;
    organizationId: string;
    balanceSummary: {
        outstanding: string;
        overdue: string;
        overdueWithoutConfirmedAddress: number;
        disputed: string;
        unchecked: number;
    } | null;
    remindersEnabled: boolean;
    canManageReminders: boolean;
    canManageMembers: boolean;
    canExportData: boolean;
    canStartCloseout: boolean;
    closeout: { exportUntil: string | null; exportAvailable: boolean } | null;
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
            {closeout && (
                <section className="w-full space-y-2 rounded-xl border p-4">
                    <h2 className="font-semibold">A cég írásvédett</h2>
                    {closeout.exportAvailable && closeout.exportUntil ? (
                        <p className="text-sm text-muted-foreground">
                            Letöltési határidő{' '}
                            <time dateTime={closeout.exportUntil}>
                                {localDate.format(
                                    new Date(closeout.exportUntil),
                                )}
                            </time>
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Az exportálási időszak lejárt.
                        </p>
                    )}
                </section>
            )}
            {balanceSummary && (
                <section className="w-full space-y-3 rounded-xl border p-4">
                    <h2 className="font-semibold">Kintlévőségek összesítése</h2>
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Ellenőrzött, nem vitatott
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {huf.format(BigInt(balanceSummary.outstanding))}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Lejárt, ellenőrzött, nem vitatott
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {huf.format(BigInt(balanceSummary.overdue))}
                            </dd>
                            <dd className="mt-1">
                                <Link
                                    className="underline"
                                    href={index(organizationId, {
                                        query: { overdue: 1 },
                                    })}
                                >
                                    Lejárt számlák megnyitása
                                </Link>
                            </dd>
                            <dd className="mt-1 text-muted-foreground">
                                A lejárt összeg a beállított magyar munkanaptár
                                éveit fedi le.
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Vitatott, ellenőrzött
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {huf.format(BigInt(balanceSummary.disputed))}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Ellenőrzésre vár
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {balanceSummary.unchecked} számla
                            </dd>
                            <dd className="mt-1">
                                <Link
                                    className="underline"
                                    href={index(organizationId, {
                                        query: { awaiting_balance: 1 },
                                    })}
                                >
                                    Egyenleg-ellenőrzésre váró számlák
                                    megnyitása
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Lejárt, ellenőrzött, nem vitatott számlák
                                megerősített pénzügyi cím nélkül
                            </dt>
                            <dd className="mt-1 font-semibold">
                                {balanceSummary.overdueWithoutConfirmedAddress}{' '}
                                számla
                            </dd>
                            <dd className="mt-1">
                                <Link
                                    className="underline"
                                    href={index(organizationId, {
                                        query: {
                                            overdue: 1,
                                            billing_unconfirmed: 1,
                                        },
                                    })}
                                >
                                    Lejárt számlák címellenőrzéshez
                                </Link>
                            </dd>
                        </div>
                    </dl>
                </section>
            )}
            {(!closeout || closeout.exportAvailable) && (
                <>
                    <Button asChild>
                        <Link href={index(organizationId)}>
                            Számlák megnyitása
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={customerList(organizationId)}>
                            Ügyfelek megnyitása
                        </Link>
                    </Button>
                    <section className="w-full space-y-3 rounded-xl border p-4">
                        <h2 className="font-semibold">Fizetési emlékeztetők</h2>
                        <Link
                            className="inline-block text-sm underline"
                            href={deliveryLog(organizationId)}
                        >
                            Tesztküldési napló
                        </Link>
                        <p
                            className="text-sm text-muted-foreground"
                            role="status"
                        >
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
                                    {...reminderDeliverySettings.form(
                                        organizationId,
                                    )}
                                    className="space-y-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="enabled"
                                                value={
                                                    remindersEnabled ? '0' : '1'
                                                }
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
                </>
            )}
            {canManageMembers && (
                <Button asChild variant="outline">
                    <Link href={members(organizationId)}>
                        Munkatársak kezelése
                    </Link>
                </Button>
            )}
            {canExportData && (
                <section className="w-full space-y-3 rounded-xl border p-4">
                    <h2 className="font-semibold">Céges adatok exportálása</h2>
                    <p className="text-sm text-muted-foreground">
                        A számlákat és ügyfeleket a customer_id oszlop kapcsolja
                        össze. Táblázatkezelőben a számlaszámot szövegként
                        importáld, hogy a kezdő nullák megmaradjanak.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a href={customers(organizationId).url} download>
                                Ügyfelek letöltése (CSV)
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={invoices(organizationId).url} download>
                                Számlák letöltése (CSV)
                            </a>
                        </Button>
                    </div>
                </section>
            )}
            {canStartCloseout && (
                <details className="w-full space-y-3 rounded-xl border p-4">
                    <summary className="cursor-pointer font-semibold">
                        Cég lezárása
                    </summary>
                    <p className="text-sm text-muted-foreground">
                        A cég írásvédetté válik, az import és az emlékeztetők
                        küldése leáll. A tulajdonos 30 napig exportálhatja az
                        adatokat.
                    </p>
                    <Form
                        {...startCloseout.form(organizationId)}
                        className="space-y-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="confirm"
                                        value="1"
                                        required
                                        className="mt-1"
                                    />
                                    Megerősítem a cég írásvédetté tételét.
                                </label>
                                <InputError
                                    message={errors.confirm}
                                    role="alert"
                                />
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Cég írásvédetté tétele
                                </Button>
                            </>
                        )}
                    </Form>
                </details>
            )}
        </div>
    );
}
