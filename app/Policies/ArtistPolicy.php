<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Artist;
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
        // Only super-admin and admin can delete
        return in_array($user->role ?? '', ['super-admin', 'admin']);
    }

    public function restore(Authenticatable $user, Artist $artist): bool
    {
        return in_array($user->role ?? '', ['super-admin', 'admin']);
    }

    public function forceDelete(Authenticatable $user, Artist $artist): bool
    {
        // Only super-admin can force delete
        return ($user->role ?? '') === 'super-admin';
    }
}
