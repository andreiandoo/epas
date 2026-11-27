<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceNotification;
use App\Services\Marketplace\OperatorNotificationJournal;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class Notifications extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Jurnalul zilei';
    protected static ?string $slug = 'notifications';
    protected static ?int $navigationSort = 100;

    // Hide from navigation since we access it through the dropdown
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.marketplace.pages.notifications';

    public ?string $filterType = null;
    public ?string $filterStatus = null;

    public function getTitle(): string|Htmlable
    {
        return 'Jurnalul zilei';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAllRead')
                ->label('Marcheaza toate citite')
                ->icon('heroicon-o-check')
                ->action(fn () => app(OperatorNotificationJournal::class)->markAllRead()),
        ];
    }

    public function getViewData(): array
    {
        $journal = app(OperatorNotificationJournal::class);
        $visibleTypes = $journal->visibleTypes($journal->admin());

        $query = $journal->query()->orderByDesc('created_at');

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->filterStatus === 'unread') {
            $query->whereIn('id', $journal->unreadQuery()->select('marketplace_notifications.id'));
        } elseif ($this->filterStatus === 'action') {
            $query->whereIn('type', OperatorNotificationJournal::ACTION_TYPES);
        }

        $notifications = $query->paginate(20);

        return [
            'notifications' => $notifications,
            'totalCount' => $journal->query()->count(),
            'unreadCount' => $journal->unreadCount(),
            'todayCount' => $journal->query()->where('created_at', '>=', now('Europe/Bucharest')->startOfDay()->utc())->count(),
            'typeLabels' => array_intersect_key(MarketplaceNotification::getTypeLabels(), array_flip($visibleTypes)),
            'typeIcons' => MarketplaceNotification::getTypeIcons(),
            'typeColors' => MarketplaceNotification::getTypeColors(),
        ];
    }

    public function markAsRead(int $id): void
    {
        app(OperatorNotificationJournal::class)->markRead($id);
    }

    public function markAsUnread(int $id): void
    {
        app(OperatorNotificationJournal::class)->markUnread($id);
    }
}
