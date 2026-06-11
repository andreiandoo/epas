<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceNotification;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class Notifications extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notificari';
    protected static ?string $slug = 'notifications';
    protected static ?int $navigationSort = 100;

    // Hide from navigation since we access it through the dropdown
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.notifications';

    public ?string $filterType = null;
    public ?string $filterStatus = null;

    public function getTitle(): string|Htmlable
    {
        return 'Notificari';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_all_read')
                ->label('Marcheaza toate citite')
                ->icon('heroicon-o-check-circle')
                ->action(function () {
                    MarketplaceNotification::where('marketplace_client_id', $this->getMarketplaceClientId())
                        ->unread()
                        ->update(['read_at' => now()]);
                }),
        ];
    }

    public function getViewData(): array
    {
        $marketplaceClientId = $this->getMarketplaceClientId();

        $query = MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->orderByDesc('created_at');

        // Apply filters
        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->filterStatus === 'unread') {
            $query->unread();
        } elseif ($this->filterStatus === 'read') {
            $query->read();
        }

        $notifications = $query->paginate(20);

        // Stats
        $totalCount = MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)->count();
        $unreadCount = MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)->unread()->count();
        $todayCount = MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->whereDate('created_at', today())->count();

        return [
            'notifications' => $notifications,
            'totalCount' => $totalCount,
            'unreadCount' => $unreadCount,
            'todayCount' => $todayCount,
            'typeLabels' => MarketplaceNotification::getTypeLabels(),
            'typeIcons' => MarketplaceNotification::getTypeIcons(),
            'typeColors' => MarketplaceNotification::getTypeColors(),
        ];
    }

    public function markAsRead(int $id): void
    {
        $notification = MarketplaceNotification::where('marketplace_client_id', $this->getMarketplaceClientId())
            ->find($id);

        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAsUnread(int $id): void
    {
        $notification = MarketplaceNotification::where('marketplace_client_id', $this->getMarketplaceClientId())
            ->find($id);

        if ($notification) {
            $notification->markAsUnread();
        }
    }

    public function deleteNotification(int $id): void
    {
        MarketplaceNotification::where('marketplace_client_id', $this->getMarketplaceClientId())
            ->where('id', $id)
            ->delete();
    }

    protected function getMarketplaceClientId(): ?int
    {
        $user = filament()->auth()->user() ?? auth('marketplace_admin')->user();

        if ($user && isset($user->marketplace_client_id)) {
            return $user->marketplace_client_id;
        }

        if (session('super_admin_marketplace_client_id')) {
            return session('super_admin_marketplace_client_id');
        }

        return null;
    }
}
