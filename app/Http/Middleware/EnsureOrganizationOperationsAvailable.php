<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationOperationsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = (string) $request->route('organization');

        if ($request->isMethodSafe()) {
            $company = $user->findActiveOrganization($organization);
            abort_unless($company->exportAvailable(), 410, 'A cég hozzáférési időszaka lejárt.');
            $request->attributes->set('organizationReadOnly', $company->isClosing());

            return $next($request);
        }

        return DB::transaction(function () use ($user, $organization, $request, $next): Response {
            $company = $user->activeOrganizations()->lockForUpdate()->findOrFail($organization);
            abort_if($company->isClosing(), 409, 'A cég írásvédett állapotban van.');

            return $next($request);
        }, attempts: 3);
    }
}
