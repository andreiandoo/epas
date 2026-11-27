<?php

namespace App\Livewire\Marketplace;

use App\Services\Marketplace\OperatorNotificationJournal;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Clopoțelul din topbar = jurnalul zilnic al operatorului (ultimele 3 zile).
 * Badge-ul și sunetul țin doar de tipurile care cer acțiune; restul sunt informative.
 */
class NotificationDropdown extends Component
{
    public bool $isOpen = false;
    public Collection $notifications;
    public int $unreadCount = 0;
    public ?int $lastActionNotificationId = null;

    protected function journal(): OperatorNotificationJournal
    {
        return app(OperatorNotificationJournal::class);
    }

    public function mount(): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        $journal = $this->journal();

        if (! $journal->clientId()) {
            $this->notifications = collect();
            $this->unreadCount = 0;
            return;
        }

        $this->notifications = $journal->query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $this->unreadCount = $journal->unreadCount();

        // Sunet doar pentru o notificare nouă care cere acțiune.
        $latestActionId = (int) ($journal->unreadQuery()->max('id') ?? 0);
        if ($this->lastActionNotificationId !== null && $latestActionId > $this->lastActionNotificationId) {
            $this->dispatch('play-notification-sound');
        }
        $this->lastActionNotificationId = $latestActionId;
    }

    #[On('notification-created')]
    public function handleNewNotification(): void
    {
        $this->loadNotifications();
    }

    public function toggleDropdown(): void
    {
        $this->isOpen = ! $this->isOpen;
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
        $this->journal()->markRead($notificationId);
        $this->loadNotifications();
    }

    public function markAllAsRead(): void
    {
        $this->journal()->markAllRead();
        $this->loadNotifications();
    }

    public function checkForNewNotifications(): void
    {
        $this->loadNotifications();
    }

    public function render()
    {
        return view('livewire.marketplace.notification-dropdown');
    }
}
