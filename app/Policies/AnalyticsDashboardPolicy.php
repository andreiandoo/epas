<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketplaceAdmin;
use App\Models\AnalyticsDashboard;
use Illuminate\Contracts\Auth\Authenticatable;

class AnalyticsDashboardPolicy
{
    // SECURITY FIX: viewAny should verify user has proper role/permissions
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $user->marketplace_client_id !== null;
        }
        return $user->tenant_id !== null || $this->isAdmin($user);
    }

    public function view(Authenticatable $user, AnalyticsDashboard $dashboard): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $dashboard->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $dashboard->tenant_id
            && ($dashboard->user_id === $user->id || $dashboard->is_shared);
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

    public function update(Authenticatable $user, AnalyticsDashboard $dashboard): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $dashboard->marketplace_client_id === $user->marketplace_client_id;
        }
        return $user->tenant_id === $dashboard->tenant_id
            && $dashboard->user_id === $user->id;
    }

    public function delete(Authenticatable $user, AnalyticsDashboard $dashboard): bool
    {
        if ($user instanceof MarketplaceAdmin) {
            return $dashboard->marketplace_client_id === $user->marketplace_client_id && !$dashboard->is_default;
        }
        return $user->tenant_id === $dashboard->tenant_id
            && $dashboard->user_id === $user->id
            && !$dashboard->is_default;
    }
}
