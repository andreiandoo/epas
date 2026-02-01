<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use Illuminate\Contracts\Auth\Authenticatable;

class UserPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        // MarketplaceAdmin cannot access User resource
        if ($user instanceof MarketplaceAdmin) {
            return false;
        }

        // SECURITY FIX: Removed temporary bypass that allowed all access
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function view(Authenticatable $user, User $record): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return false;
        }
        return $this->viewAny($user);
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return false;
        }
        return $user->isAdmin() || $user->isSuperAdmin() || $user->isTenant();
    }

    public function update(Authenticatable $user, User $record): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return false;
        }
        // admin NU poate edita super-admin
        if ($record->isSuperAdmin() && !$user->isSuperAdmin()) return false;
        return $this->viewAny($user);
    }

    public function delete(Authenticatable $user, User $record): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return false;
        }
        // nimeni nu poate șterge super-admin
        if ($record->isSuperAdmin()) return false;
        return $user->isSuperAdmin();
    }
}
