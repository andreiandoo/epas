<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceNotification;
use App\Services\MarketplaceNotificationService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\On;

class NotificationDropdown extends Component
{
    public bool $isOpen = false;
    public Collection $notifications;
    public int $unreadCount = 0;
    public ?int $lastNotificationId = null;

    protected MarketplaceNotificationService $notificationService;

    public function boot(MarketplaceNotificationService $notificationService): void
    {
        $this->notificationService = $notificationService;
    }

    public function mount(): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        $marketplaceClientId = $this->getMarketplaceClientId();

        if (!$marketplaceClientId) {
            $this->notifications = collect();
            $this->unreadCount = 0;
            return;
        }

        $this->notifications = MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $this->unreadCount = MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
            ->unread()
            ->count();

        // Track the latest notification ID to detect new ones
        if ($this->notifications->isNotEmpty()) {
            $newLastId = $this->notifications->first()->id;
            if ($this->lastNotificationId !== null && $newLastId > $this->lastNotificationId) {
                // New notification detected - play sound
                $this->dispatch('play-notification-sound');
            }
            $this->lastNotificationId = $newLastId;
        }
    }

    #[On('notification-created')]
    public function handleNewNotification(): void
    {
        $this->loadNotifications();
        $this->dispatch('play-notification-sound');
    }

    public function toggleDropdown(): void
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen) {
            $this->loadNotifications();
        }
    }

    public function closeDropdown(): void
    {
        $this->isOpen = false;
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = MarketplaceNotification::find($notificationId);
        if ($notification && $notification->marketplace_client_id === $this->getMarketplaceClientId()) {
            $notification->markAsRead();
            $this->loadNotifications();
        }
    }

    public function markAllAsRead(): void
    {
        $marketplaceClientId = $this->getMarketplaceClientId();
        if ($marketplaceClientId) {
            MarketplaceNotification::where('marketplace_client_id', $marketplaceClientId)
                ->unread()
                ->update(['read_at' => now()]);
            $this->loadNotifications();
        }
    }

    /**
     * Poll for new notifications every 30 seconds
     */
    public function checkForNewNotifications(): void
    {
        $this->loadNotifications();
    }

    protected function getMarketplaceClientId(): ?int
    {
        // Use Filament's auth in panel context
        $user = filament()->auth()->user() ?? auth('marketplace_admin')->user();

        if ($user && isset($user->marketplace_client_id)) {
            return $user->marketplace_client_id;
        }

        // Check for super-admin mode
        if (session('super_admin_marketplace_client_id')) {
            return session('super_admin_marketplace_client_id');
        }

        return null;
    }

    public function render()
    {
        return view('livewire.marketplace.notification-dropdown');
    }
}
