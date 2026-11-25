<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function approveReminder(User $user, Organization $organization): bool
    {
        return ! $organization->isClosing() && $this->isActiveOwner($user, $organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return ! $organization->isClosing() && $this->isActiveOwner($user, $organization);
    }

    public function exportData(User $user, Organization $organization): bool
    {
        return $this->isActiveOwner($user, $organization);
    }

    public function closeout(User $user, Organization $organization): bool
    {
        return $this->isActiveOwner($user, $organization);
    }

    private function isActiveOwner(User $user, Organization $organization): bool
    {
        return $user->email_verified_at !== null
            && $user->activeOrganizations()
                ->wherePivot('role', 'owner')
                ->whereKey($organization->id)
                ->exists();
    }
}
