<?php

namespace App\Filament\Marketplace\Widgets;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Chat\ChatConversation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ChatStatsWidget extends StatsOverviewWidget
{
    use HasMarketplaceContext;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return (bool) (Auth::guard('marketplace_admin')->user()?->marketplaceClient?->hasMicroservice('live-chat'));
    }

    protected function getStats(): array
    {
        $clientId = Auth::guard('marketplace_admin')->user()?->marketplace_client_id;

        if ($clientId === null) {
            return [];
        }

        $base = fn () => ChatConversation::query()->where('marketplace_client_id', $clientId);

        $active = $base()->where('status', 'active')->count();
        $unclaimed = $base()->whereIn('status', ['queued', 'offline_message'])->count();
        $today = $base()->whereDate('created_at', Carbon::today())->count();

        return [
            Stat::make('Chat-uri active', $active)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->url('/marketplace/chat-console'),

            Stat::make('Chat-uri nepreluate', $unclaimed)
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($unclaimed > 0 ? 'warning' : 'gray')
                ->url('/marketplace/chat-console'),

            Stat::make('Chat-uri azi', $today)
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->url('/marketplace/chat-console'),
        ];
    }
}
