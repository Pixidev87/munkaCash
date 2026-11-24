import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index } from '@/routes/organizations/invoices';

export default function OrganizationPage({
    name,
    organizationId,
}: {
    name: string;
    organizationId: string;
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
        </div>
    );
}
