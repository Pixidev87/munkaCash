import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { accessLog, show } from '@/routes/organizations';
import { destroy } from '@/routes/organizations/members';
import {
    destroy as cancelInvitation,
    store,
} from '@/routes/organizations/members/invitations';

type Member = { id: string; name: string; email: string };

type Props = {
    organization: { id: string; name: string };
    members: {
        data: Member[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
};

export default function Members({ organization, members }: Props) {
    const [invitationLink, setInvitationLink] = useState('');
    const [cancellationStatus, setCancellationStatus] = useState('');
    return (
        <div className="w-full max-w-3xl space-y-4 p-4 sm:p-6">
            <Head title={`Munkatársak · ${organization.name}`} />
            <Button variant="outline" asChild>
                <Link href={show(organization.id)}>Vissza a céghez</Link>
            </Button>
            <h1 className="text-2xl font-semibold tracking-tight">
                Munkatársak
            </h1>
            <p className="text-sm wrap-break-word text-muted-foreground">
                {organization.name}
            </p>
            <Link
                className="inline-block text-sm underline"
                href={accessLog(organization.id)}
            >
                Hozzáférési napló
            </Link>
            <section className="space-y-3 rounded-xl border p-4">
                <h2 className="font-semibold">Munkatárs meghívása</h2>
                <Form
                    {...store.form(organization.id)}
                    resetOnSuccess
                    onStart={() => {
                        setInvitationLink('');
                        setCancellationStatus('');
                    }}
                    onSuccess={(page) => {
                        const path = page.flash.invitationPath;
                        if (typeof path === 'string') {
                            setInvitationLink(
                                new URL(path, window.location.origin).href,
                            );
                        }
                    }}
                    className="space-y-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="invite-email">
                                Meghívott e-mail-címe
                            </Label>
                            <Input
                                id="invite-email"
                                name="email"
                                type="email"
                                maxLength={255}
                                required
                            />
                            <InputError message={errors.email} role="alert" />
                            <Button type="submit" disabled={processing}>
                                Meghívólink létrehozása
                            </Button>
                        </>
                    )}
                </Form>
                {invitationLink && (
                    <div className="space-y-2">
                        <Label htmlFor="invitation-link">
                            Megosztható meghívólink
                        </Label>
                        <Input
                            id="invitation-link"
                            readOnly
                            value={invitationLink}
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <p className="text-sm text-muted-foreground">
                            Küldd el a meghívottnak. A link egyszer használható
                            és 24 óráig érvényes.
                        </p>
                    </div>
                )}
            </section>
            <details className="space-y-3 rounded-xl border p-4">
                <summary className="cursor-pointer font-semibold">
                    Függő meghívó visszavonása
                </summary>
                <Form
                    {...cancelInvitation.form(organization.id)}
                    errorBag="cancelInvitation"
                    resetOnSuccess
                    onStart={() => setCancellationStatus('')}
                    onSuccess={(page) => {
                        if (page.flash.invitationCancellation === 'cancelled') {
                            setInvitationLink('');
                            setCancellationStatus(
                                'A meghívólinket visszavontad.',
                            );
                        } else if (
                            page.flash.invitationCancellation === 'not_found'
                        ) {
                            setCancellationStatus(
                                'Ehhez a címhez nincs érvényes meghívó.',
                            );
                        }
                    }}
                    className="space-y-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="cancel-invite-email">
                                A meghívó e-mail-címe
                            </Label>
                            <Input
                                id="cancel-invite-email"
                                name="email"
                                type="email"
                                maxLength={255}
                                required
                            />
                            <InputError message={errors.email} role="alert" />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                Meghívólink visszavonása
                            </Button>
                        </>
                    )}
                </Form>
                <p className="text-sm text-muted-foreground" role="status">
                    {cancellationStatus}
                </p>
            </details>
            {members.data.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nincs aktív munkatárs ezen az oldalon.
                </p>
            ) : (
                <ul className="divide-y rounded-xl border">
                    {members.data.map((member) => (
                        <li key={member.id} className="space-y-3 p-4">
                            <div>
                                <h2 className="font-semibold wrap-break-word">
                                    {member.name}
                                </h2>
                                <p className="text-sm wrap-break-word text-muted-foreground">
                                    {member.email}
                                </p>
                            </div>
                            <details className="space-y-3">
                                <summary className="cursor-pointer text-sm font-medium">
                                    Hozzáférés visszavonása
                                </summary>
                                <Form
                                    {...destroy.form({
                                        organization: organization.id,
                                        member: member.id,
                                    })}
                                    errorBag={`member-${member.id}`}
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
                                                Megerősítem a munkatárs
                                                hozzáférésének visszavonását
                                                ennél a cégnél.
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
                                                Visszavonás megerősítése
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </details>
                        </li>
                    ))}
                </ul>
            )}
            {(members.prev_page_url || members.next_page_url) && (
                <nav
                    aria-label="Munkatársak lapozása"
                    className="flex gap-4 text-sm"
                >
                    {members.prev_page_url && (
                        <Link
                            className="underline"
                            href={members.prev_page_url}
                        >
                            Előző oldal
                        </Link>
                    )}
                    {members.next_page_url && (
                        <Link
                            className="underline"
                            href={members.next_page_url}
                        >
                            Következő oldal
                        </Link>
                    )}
                </nav>
            )}
        </div>
    );
}
