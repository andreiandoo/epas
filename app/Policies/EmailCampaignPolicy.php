<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EmailCampaign;

class EmailCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmailCampaign $campaign): bool
    {
        return $user->tenant_id === $campaign->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, EmailCampaign $campaign): bool
    {
        return $user->tenant_id === $campaign->tenant_id
            && in_array($campaign->status, ['draft', 'scheduled']);
    }

    public function delete(User $user, EmailCampaign $campaign): bool
    {
        return $user->tenant_id === $campaign->tenant_id
            && $campaign->status === 'draft';
    }

    public function send(User $user, EmailCampaign $campaign): bool
    {
        return $user->tenant_id === $campaign->tenant_id
            && in_array($campaign->status, ['draft', 'scheduled']);
    }
}
