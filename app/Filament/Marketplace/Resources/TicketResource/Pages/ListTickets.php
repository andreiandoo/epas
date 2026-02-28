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
    protected static string $resource = TicketResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Bilete <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => { const header = document.querySelector('.fi-header'); if (!header) return; const actions = header.querySelector('.fi-header-actions-ctn'); if (actions) header.insertBefore(\$el, actions); else header.appendChild(\$el); \$el.style.flex = '1'; \$el.style.minWidth = '0'; const nav = \$el.querySelector('.fi-tabs'); if (nav) { nav.style.marginInline = 'unset'; nav.style.marginLeft = 'auto'; nav.style.marginRight = '0'; } })",
            ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toate'),
            'valid' => Tab::make('Valide')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'valid'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'valid')->count())
                ->badgeColor('success'),
            'used' => Tab::make('Utilizate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'used'))
                ->badgeColor('warning'),
            'cancelled' => Tab::make('Anulate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled')),
            'refunded' => Tab::make('Rambursate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'refunded')),
        ];
    }
}
