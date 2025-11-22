<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Venue $venue): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Venue $venue): bool { return true; }
    public function delete(User $user, Venue $venue): bool { return true; }

    public function restore(User $user, Venue $venue): bool { return false; }
    public function forceDelete(User $user, Venue $venue): bool { return false; }
}
