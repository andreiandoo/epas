<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistEntry;

class WaitlistEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WaitlistEntry $entry): bool
    {
        return $user->tenant_id === $entry->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WaitlistEntry $entry): bool
    {
        return $user->tenant_id === $entry->tenant_id;
    }

    public function delete(User $user, WaitlistEntry $entry): bool
    {
        return $user->tenant_id === $entry->tenant_id;
    }
}
