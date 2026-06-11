<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Artist;
use App\Models\MarketplaceAdmin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * SECURITY FIX: Added proper authorization checks
 * Previously all methods returned true allowing anyone to do anything
 */
class ArtistPolicy
{
    /**
     * Check if user has admin privileges
     */
    protected function isAdmin(Authenticatable $user): bool
    {
        // Marketplace admin users — always allowed (scoped by marketplace_client_id in resource)
        if ($user instanceof MarketplaceAdmin) {
            return true;
        }

        return in_array($user->role ?? '', ['super-admin', 'admin', 'editor']);
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(Authenticatable $user, Artist $artist): bool
    {
        return $this->isAdmin($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(Authenticatable $user, Artist $artist): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(Authenticatable $user, Artist $artist): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return in_array($user->role ?? '', ['super_admin', 'admin']);
        }
        return in_array($user->role ?? '', ['super-admin', 'admin']);
    }

    public function restore(Authenticatable $user, Artist $artist): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return in_array($user->role ?? '', ['super_admin', 'admin']);
        }
        return in_array($user->role ?? '', ['super-admin', 'admin']);
    }

    public function forceDelete(Authenticatable $user, Artist $artist): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return ($user->role ?? '') === 'super_admin';
        }
        return ($user->role ?? '') === 'super-admin';
    }
}
