import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { accept } from '@/routes/organization-invitations';

export default function AcceptInvitation({
    companyName,
    token,
}: {
    companyName: string;
    token: string;
}) {
    return (
        <div className="w-full max-w-xl space-y-4 p-4 sm:p-6">
            <Head title="Munkatársi meghívó" />
            <h1 className="text-2xl font-semibold tracking-tight">
                Munkatársi meghívó
            </h1>
            <p className="wrap-break-word">{companyName}</p>
            <Form {...accept.form(token)}>
                {({ processing }) => (
                    <Button type="submit" disabled={processing}>
                        Meghívó elfogadása
                    </Button>
                )}
            </Form>
        </div>
    );
}
