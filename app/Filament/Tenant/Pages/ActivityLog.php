<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page
{
    protected static $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.tenant.pages.activity-log';

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

        // Get activities related to this tenant
        $activities = Activity::where(function ($query) use ($tenant) {
                $query->where('subject_type', 'App\\Models\\Tenant')
                      ->where('subject_id', $tenant->id);
            })
            ->orWhere(function ($query) use ($tenant) {
                $query->where('causer_type', 'App\\Models\\User')
                      ->where('causer_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return [
            'activities' => $activities,
        ];
    }
}
