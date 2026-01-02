<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\EmailCampaign;
use Illuminate\Contracts\Auth\Authenticatable;

class EmailCampaignPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, EmailCampaign $campaign): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $campaign->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $campaign->tenant_id;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
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
