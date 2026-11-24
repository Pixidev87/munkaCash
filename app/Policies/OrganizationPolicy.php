<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function approveReminder(User $user, Organization $organization): bool
    {
        return $user->email_verified_at !== null
            && $user->activeOrganizations()
                ->wherePivot('role', 'owner')
                ->whereKey($organization->id)
                ->exists();
    }
}
