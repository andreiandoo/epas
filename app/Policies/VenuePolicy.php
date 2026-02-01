<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * SECURITY FIX: Added proper authorization checks
 * Previously all methods returned true allowing anyone to do anything
 */
class VenuePolicy
{
    /**
     * Check if user has admin privileges
     */
    protected function isAdmin(Authenticatable $user): bool
    {
        return in_array($user->role ?? '', ['super-admin', 'admin', 'editor']);
    }

    /**
     * Check if user belongs to the same tenant as the venue
     */
    protected function belongsToTenant(Authenticatable $user, Venue $venue): bool
    {
        // If user has tenant_id, check if it matches venue's tenant_id
        if (isset($user->tenant_id) && isset($venue->tenant_id)) {
            return $user->tenant_id === $venue->tenant_id;
        }

        // Super-admin can access all venues
        if (($user->role ?? '') === 'super-admin') {
            return true;
        }

        return false;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(Authenticatable $user, Venue $venue): bool
    {
        return $this->isAdmin($user) && $this->belongsToTenant($user, $venue);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(Authenticatable $user, Venue $venue): bool
    {
        return $this->isAdmin($user) && $this->belongsToTenant($user, $venue);
    }

    public function delete(Authenticatable $user, Venue $venue): bool
    {
        // Only super-admin and admin can delete
        return in_array($user->role ?? '', ['super-admin', 'admin']) && $this->belongsToTenant($user, $venue);
    }

    public function restore(Authenticatable $user, Venue $venue): bool
    {
        return in_array($user->role ?? '', ['super-admin', 'admin']) && $this->belongsToTenant($user, $venue);
    }

    public function forceDelete(Authenticatable $user, Venue $venue): bool
    {
        // Only super-admin can force delete
        return ($user->role ?? '') === 'super-admin';
    }
}
