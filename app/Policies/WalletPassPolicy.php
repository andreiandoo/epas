<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\WalletPass;
use Illuminate\Contracts\Auth\Authenticatable;

class WalletPassPolicy
{
    // SECURITY FIX: viewAny should verify user has proper role/permissions
    public function viewAny(Authenticatable $user): bool
    {
        // Allow if user is admin or has tenant/marketplace association
        if ($user instanceof MarketplaceAdmin) {
            return $user->marketplace_client_id !== null;
        }
        return $user->tenant_id !== null || $this->isAdmin($user);
    }

    public function view(Authenticatable $user, WalletPass $pass): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $pass->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $pass->tenant_id;
    }

    // SECURITY FIX: create should verify user has proper role/permissions
    public function create(Authenticatable $user): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $user->marketplace_client_id !== null;
        }
        return $user->tenant_id !== null || $this->isAdmin($user);
    }

    private function isAdmin(Authenticatable $user): bool
    {
        return in_array($user->role ?? '', ['super-admin', 'admin', 'editor']);
    }

    public function update(Authenticatable $user, WalletPass $pass): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $pass->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $pass->tenant_id;
    }

    public function delete(Authenticatable $user, WalletPass $pass): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $pass->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $pass->tenant_id;
    }
}
