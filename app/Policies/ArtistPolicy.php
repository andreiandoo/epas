<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Artist;

class ArtistPolicy
{
    // Dacă ai un câmp is_admin pe users, poți debloca tot pentru admin:
    // public function before(User $user, string $ability)
    // {
    //     return $user->is_admin ? true : null;
    // }

    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Artist $artist): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Artist $artist): bool { return true; }
    public function delete(User $user, Artist $artist): bool { return true; }

    public function restore(User $user, Artist $artist): bool { return false; }
    public function forceDelete(User $user, Artist $artist): bool { return false; }
}
