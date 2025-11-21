<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GroupBooking;

class GroupBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, GroupBooking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, GroupBooking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id;
    }

    public function delete(User $user, GroupBooking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id
            && $booking->status === 'pending';
    }

    public function confirm(User $user, GroupBooking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id
            && $booking->status === 'pending';
    }
}
