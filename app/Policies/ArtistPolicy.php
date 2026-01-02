<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Artist;
use Illuminate\Contracts\Auth\Authenticatable;

class ArtistPolicy
{
    public function viewAny(Authenticatable $user): bool { return true; }
    public function view(Authenticatable $user, Artist $artist): bool { return true; }
    public function create(Authenticatable $user): bool { return true; }
    public function update(Authenticatable $user, Artist $artist): bool { return true; }
    public function delete(Authenticatable $user, Artist $artist): bool { return true; }

    public function restore(Authenticatable $user, Artist $artist): bool { return false; }
    public function forceDelete(Authenticatable $user, Artist $artist): bool { return false; }
}
