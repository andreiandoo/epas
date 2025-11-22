<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WalletPass;

class WalletPassPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WalletPass $pass): bool
    {
        return $user->tenant_id === $pass->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WalletPass $pass): bool
    {
        return $user->tenant_id === $pass->tenant_id;
    }

    public function delete(User $user, WalletPass $pass): bool
    {
        return $user->tenant_id === $pass->tenant_id;
    }
}
