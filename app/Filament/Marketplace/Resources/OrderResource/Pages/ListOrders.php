<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Comenzi <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
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
            'completed' => Tab::make('Finalizate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'completed')->count())
                ->badgeColor('success'),
            'paid' => Tab::make('Plătite')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'paid')->count())
                ->badgeColor('success'),
            'pending' => Tab::make('În așteptare')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'failed' => Tab::make('Eșuate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'failed')->count())
                ->badgeColor('danger'),
            'cancelled' => Tab::make('Anulate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled')),
            'refunded' => Tab::make('Rambursate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'refunded')),
            'expired' => Tab::make('Expirate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'expired')),
        ];
    }
}
