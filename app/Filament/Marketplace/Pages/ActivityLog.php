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

        // Get tenant IDs associated with this marketplace
        $tenantIds = $marketplace->tenants()->pluck('tenants.id')->toArray();

        $activities = Activity::where(function ($query) use ($marketplace, $tenantIds) {
                // Activities for tenants belonging to this marketplace
                if (!empty($tenantIds)) {
                    $query->whereIn('properties->tenant_id', $tenantIds);
                }

                // Activities caused by marketplace users
                $query->orWhere(function ($q) use ($marketplace) {
                    $q->where('causer_type', 'App\\Models\\User')
                      ->whereIn('causer_id', function ($subQuery) use ($marketplace) {
                          $subQuery->select('id')
                              ->from('users')
                              ->where('marketplace_client_id', $marketplace->id);
                      });
                });

                // Activities that explicitly logged marketplace_client_id
                $query->orWhere('properties->marketplace_client_id', $marketplace->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return [
            'activities' => $activities,
        ];
    }
}
