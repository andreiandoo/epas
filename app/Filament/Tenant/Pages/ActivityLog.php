<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.tenant.pages.activity-log';

    public function getTitle(): string
    {
        return 'Activity Log';
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return ['activities' => collect()];
        }

        // Get activities for this tenant from the 'tenant' log channel
        // Activities are scoped via tenant_id stored in properties JSON
        $activities = Activity::where('log_name', 'tenant')
            ->where(function ($query) use ($tenant) {
                // Match by tenant_id in properties JSON
                $query->whereJsonContains('properties->tenant_id', $tenant->id)
                    // Also match activities caused by users belonging to this tenant
                    ->orWhere(function ($q) use ($tenant) {
                        $q->where('causer_type', 'App\\Models\\User')
                          ->whereIn('causer_id', function ($subQuery) use ($tenant) {
                              $subQuery->select('id')
                                  ->from('users')
                                  ->where('tenant_id', $tenant->id);
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
