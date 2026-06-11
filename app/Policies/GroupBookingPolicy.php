<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\GroupBooking;
use Illuminate\Contracts\Auth\Authenticatable;

class GroupBookingPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, GroupBooking $booking): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $booking->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $booking->tenant_id;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, GroupBooking $booking): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $booking->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $booking->tenant_id;
    }

    public function delete(Authenticatable $user, GroupBooking $booking): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $booking->marketplace_client_id === $user->marketplace_client_id
                && $booking->status === 'pending';
        }
        return $user->tenant_id === $booking->tenant_id
            && $booking->status === 'pending';
    }

    public function confirm(Authenticatable $user, GroupBooking $booking): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $booking->marketplace_client_id === $user->marketplace_client_id
                && $booking->status === 'pending';
        }
        return $user->tenant_id === $booking->tenant_id
            && $booking->status === 'pending';
    }
}
