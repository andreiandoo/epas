<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static \UnitEnum|string|null $navigationGroup = 'Help';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.marketplace.pages.activity-log';

    public function getTitle(): string
    {
        return 'Activity Log';
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return ['activities' => collect()];
        }

        // Determine which tenant IDs this marketplace can access
        // Priority: allowed_tenants JSON > pivot table > all tenants (when null)
        $tenantIds = null; // null means "all tenants"

        if (!is_null($marketplace->allowed_tenants)) {
            // Explicit tenant list from JSON column
            $tenantIds = $marketplace->allowed_tenants;
        }

        // Also merge in tenants from pivot table
        $pivotTenantIds = $marketplace->tenants()->pluck('tenants.id')->toArray();
        if (!empty($pivotTenantIds)) {
            $tenantIds = array_unique(array_merge($tenantIds ?? [], $pivotTenantIds));
        }

        $activities = Activity::where('log_name', 'tenant')
            ->where(function ($query) use ($marketplace, $tenantIds) {
                if (is_null($tenantIds)) {
                    // Marketplace has access to ALL tenants — show all tenant activities
                    $query->whereNotNull('id');
                } else {
                    // Show activities for specific tenants using whereJsonContains
                    $query->where(function ($q) use ($tenantIds) {
                        foreach ($tenantIds as $tenantId) {
                            $q->orWhereJsonContains('properties->tenant_id', (int) $tenantId);
                        }
                    });
                }

                // Also include activities caused by marketplace users
                $query->orWhere(function ($q) use ($marketplace) {
                    $q->where('causer_type', 'App\\Models\\User')
                      ->whereIn('causer_id', function ($subQuery) use ($marketplace) {
                          $subQuery->select('id')
                              ->from('users')
                              ->where('marketplace_client_id', $marketplace->id);
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'activities' => $activities,
        ];
    }
}
