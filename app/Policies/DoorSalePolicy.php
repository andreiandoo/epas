<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\DoorSale;
use Illuminate\Contracts\Auth\Authenticatable;

class DoorSalePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, DoorSale $doorSale): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $doorSale->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $doorSale->tenant_id;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
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
