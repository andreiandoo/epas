<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\WalletPass;
use Illuminate\Contracts\Auth\Authenticatable;

class WalletPassPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, WalletPass $pass): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $pass->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $pass->tenant_id;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, WalletPass $pass): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $pass->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $pass->tenant_id;
    }

    public function delete(Authenticatable $user, WalletPass $pass): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $pass->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $pass->tenant_id;
    }
}
