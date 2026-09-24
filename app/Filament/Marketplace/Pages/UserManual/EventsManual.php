<?php

namespace App\Filament\Marketplace\Pages\UserManual;

use App\Support\Manual\PageManual;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * Full-page view of the event page manual. Same content as the "Manual pagină"
 * drawer on the event edit page: resources/manual/marketplace/event-edit.
 */
class EventsManual extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'manual-events';

    protected string $view = 'filament.marketplace.pages.user-manual.page-manual';

    public function getTitle(): string
    {
        return 'Manual: ' . PageManual::config('event-edit')['title'];
    }

    public function getBreadcrumbs(): array
    {
        return [
            UserManualIndex::getUrl() => 'Manual Utilizator',
            '#' => $this->getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_hub')
                ->label('Înapoi la Manual')
                ->url(UserManualIndex::getUrl())
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getViewData(): array
    {
        return ['manual' => PageManual::load('event-edit')];
    }
}
