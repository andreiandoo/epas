<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Services\Marketplace\AllTimeStatsService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AllTimeStats extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Statistici All Time';

    protected static ?string $title = 'Statistici All Time';

    protected static ?int $navigationSort = 99;

    // Reached via a button on the dashboard, not the sidebar.
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.all-time-stats';

    public ?MarketplaceClient $marketplace = null;

    /**
     * All-time detailed stats mirror the dashboard's super-admin-only block.
     */
    public static function canAccess(): bool
    {
        return Auth::guard('marketplace_admin')->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;
    }

    public function getTitle(): string
    {
        return 'Statistici All Time';
    }

    public function getViewData(): array
    {
        $marketplace = $this->marketplace;
        if (! $marketplace) {
            return ['marketplace' => null, 'stats' => [], 'breakdown' => null, 'currency' => 'RON'];
        }

        $marketplaceId = $marketplace->id;
        $svc = app(AllTimeStatsService::class);

        // Same 30-min cache window the dashboard used for these cards.
        $stats = Cache::remember(
            "mp_alltime_cards_{$marketplaceId}",
            1800,
            fn () => $svc->cards($marketplaceId, $marketplace)
        );
        $breakdown = Cache::remember(
            "mp_alltime_breakdown_{$marketplaceId}",
            1800,
            fn () => $svc->breakdown($marketplaceId)
        );

        return [
            'marketplace' => $marketplace,
            'stats' => $stats,
            'breakdown' => $breakdown,
            'currency' => $marketplace->currency ?? 'RON',
        ];
    }
}
