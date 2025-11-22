<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AnalyticsDashboard;

class AnalyticsDashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $user->tenant_id === $dashboard->tenant_id
            && ($dashboard->user_id === $user->id || $dashboard->is_shared);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $user->tenant_id === $dashboard->tenant_id
            && $dashboard->user_id === $user->id;
    }

    public function delete(User $user, AnalyticsDashboard $dashboard): bool
    {
        return $user->tenant_id === $dashboard->tenant_id
            && $dashboard->user_id === $user->id
            && !$dashboard->is_default;
    }
}
