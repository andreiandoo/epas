<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\DoorSale;
use Illuminate\Contracts\Auth\Authenticatable;

class DoorSalePolicy
{
    // SECURITY FIX: viewAny should verify user has proper role/permissions
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $user->marketplace_client_id !== null;
        }
        return $user->tenant_id !== null || $this->isAdmin($user);
    }

    public function view(Authenticatable $user, DoorSale $doorSale): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $doorSale->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $doorSale->tenant_id;
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

    public function refund(Authenticatable $user, DoorSale $doorSale): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $doorSale->marketplace_client_id === $user->marketplace_client_id && $doorSale->canRefund();
        }
        return $user->tenant_id === $doorSale->tenant_id
            && $doorSale->canRefund();
    }

    public function resend(Authenticatable $user, DoorSale $doorSale): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $doorSale->marketplace_client_id === $user->marketplace_client_id
                && $doorSale->status === DoorSale::STATUS_COMPLETED;
        }
        return $user->tenant_id === $doorSale->tenant_id
            && $doorSale->status === DoorSale::STATUS_COMPLETED;
    }
}
