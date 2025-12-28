<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        // TEMPORARY: Allow all for testing
        return true;

        // Original:
        // return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function view(User $user, User $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin() || $user->isTenant();
    }

    public function update(User $user, User $record): bool
    {
        // admin NU poate edita super-admin
        if ($record->isSuperAdmin() && !$user->isSuperAdmin()) return false;
        return $this->viewAny($user);
    }

    public function delete(User $user, User $record): bool
    {
        // nimeni nu poate șterge super-admin
        if ($record->isSuperAdmin()) return false;
        return $user->isSuperAdmin();
    }
}
