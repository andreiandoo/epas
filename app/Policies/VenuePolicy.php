<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\Auth\Authenticatable;

class VenuePolicy
{
    public function viewAny(Authenticatable $user): bool { return true; }
    public function view(Authenticatable $user, Venue $venue): bool { return true; }
    public function create(Authenticatable $user): bool { return true; }
    public function update(Authenticatable $user, Venue $venue): bool { return true; }
    public function delete(Authenticatable $user, Venue $venue): bool { return true; }

    public function restore(Authenticatable $user, Venue $venue): bool { return false; }
    public function forceDelete(Authenticatable $user, Venue $venue): bool { return false; }
}
