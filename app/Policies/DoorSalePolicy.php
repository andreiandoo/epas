<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DoorSale;

class DoorSalePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DoorSale $doorSale): bool
    {
        return $user->tenant_id === $doorSale->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function refund(User $user, DoorSale $doorSale): bool
    {
        return $user->tenant_id === $doorSale->tenant_id
            && $doorSale->canRefund();
    }

    public function resend(User $user, DoorSale $doorSale): bool
    {
        return $user->tenant_id === $doorSale->tenant_id
            && $doorSale->status === DoorSale::STATUS_COMPLETED;
    }
}
