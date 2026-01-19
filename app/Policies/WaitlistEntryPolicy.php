<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\WaitlistEntry;
use Illuminate\Contracts\Auth\Authenticatable;

class WaitlistEntryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, WaitlistEntry $entry): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $entry->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $entry->tenant_id;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, WaitlistEntry $entry): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $entry->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $entry->tenant_id;
    }

    public function delete(Authenticatable $user, WaitlistEntry $entry): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $entry->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $entry->tenant_id;
    }
}
