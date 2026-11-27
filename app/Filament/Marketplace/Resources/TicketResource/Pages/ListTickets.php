<?php

namespace App\Filament\Marketplace\Resources\TicketResource\Pages;

use App\Filament\Marketplace\Resources\TicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListTickets extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = TicketResource::class;

    public function mount(): void
    {
        parent::mount();

        $eventId = request()->query('event_id');
        if ($eventId) {
            $this->tableFilters['event_id']['event_id'] = $eventId;
        }
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Bilete'));
    }

    /** Cancelled / refunded are rare: one dropdown next to the main views. */
    protected function listTodoTabKeys(): array
    {
        return ['cancelled', 'refunded'];
    }

    protected function listTodoTabsLabel(): string
    {
        return __('Alte stări');
    }
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('Toate'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->count())
                ->badgeColor('gray'),
            'valid' => Tab::make(__('Valide'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['valid', 'used']))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereIn('status', ['valid', 'used'])->count())
                ->badgeColor('success'),
            'used' => Tab::make(__('Utilizate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'used'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'used')->count())
                ->badgeColor('warning'),
            'cancelled' => Tab::make(__('Anulate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'cancelled')->count())
                ->badgeColor('danger'),
            'refunded' => Tab::make(__('Rambursate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'refunded'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'refunded')->count())
                ->badgeColor('gray'),
        ];
    }
}
