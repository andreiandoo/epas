<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\EmailCampaign;
use Illuminate\Contracts\Auth\Authenticatable;

class EmailCampaignPolicy
{
    // SECURITY FIX: viewAny should verify user has proper role/permissions
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $user->marketplace_client_id !== null;
        }
        return $user->tenant_id !== null || $this->isAdmin($user);
    }

    public function view(Authenticatable $user, EmailCampaign $campaign): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $campaign->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $campaign->tenant_id;
    }

    // SECURITY FIX: create should verify user has proper role/permissions
    public function create(Authenticatable $user): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $user->marketplace_client_id !== null;
        }
        return $user->tenant_id !== null || $this->isAdmin($user);
    }

    private function isAdmin(Authenticatable $user): bool
    {
        return in_array($user->role ?? '', ['super-admin', 'admin', 'editor']);
    }

    public function update(Authenticatable $user, EmailCampaign $campaign): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $campaign->marketplace_client_id === $user->marketplace_client_id
                && in_array($campaign->status, ['draft', 'scheduled']);
        }
        return $user->tenant_id === $campaign->tenant_id
            && in_array($campaign->status, ['draft', 'scheduled']);
    }

    public function delete(Authenticatable $user, EmailCampaign $campaign): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $campaign->marketplace_client_id === $user->marketplace_client_id
                && $campaign->status === 'draft';
        }
        return $user->tenant_id === $campaign->tenant_id
            && $campaign->status === 'draft';
    }

    public function send(Authenticatable $user, EmailCampaign $campaign): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $campaign->marketplace_client_id === $user->marketplace_client_id
                && in_array($campaign->status, ['draft', 'scheduled']);
        }
        return $user->tenant_id === $campaign->tenant_id
            && in_array($campaign->status, ['draft', 'scheduled']);
    }
}
