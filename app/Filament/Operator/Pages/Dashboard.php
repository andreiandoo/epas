<?php

namespace App\Filament\Operator\Pages;

use App\Models\Leisure\ResourceRental;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';
    protected static \UnitEnum|string|null $navigationGroup = 'Operațiuni';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Astăzi';
    protected static ?string $slug = '/';

    protected string $view = 'filament.operator.dashboard';

    public function getViewData(): array
    {
        $user = auth()->user();
        $teamMember = $user?->teamMember ?? null;
        $tenantId = $teamMember?->tenant_id;

        $today = now()->startOfDay();
        $tomorrow = now()->endOfDay();

        $activeRentals = $tenantId ? ResourceRental::where('tenant_id', $tenantId)
            ->whereNull('ended_at')
            ->count() : 0;

        $overdueRentals = $tenantId ? ResourceRental::where('tenant_id', $tenantId)
            ->whereNull('ended_at')
            ->where('planned_end_at', '<', now())
            ->count() : 0;

        $rentalsTodayCompleted = $tenantId ? ResourceRental::where('tenant_id', $tenantId)
            ->whereBetween('ended_at', [$today, $tomorrow])
            ->count() : 0;

        return [
            'teamMember' => $teamMember,
            'tenantName' => $teamMember?->tenant?->public_name ?? $teamMember?->tenant?->name,
            'leisureRole' => $teamMember?->leisure_role,
            'stats' => [
                'active_rentals' => $activeRentals,
                'overdue_rentals' => $overdueRentals,
                'rentals_today' => $rentalsTodayCompleted,
            ],
        ];
    }
}
