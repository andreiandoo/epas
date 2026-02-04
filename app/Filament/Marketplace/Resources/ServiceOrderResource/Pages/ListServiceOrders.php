<?php

namespace App\Filament\Marketplace\Resources\ServiceOrderResource\Pages;

use App\Filament\Marketplace\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServiceOrders extends ListRecords
{
    protected static string $resource = ServiceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
            'all' => Tab::make('All'),
            'pending_payment' => Tab::make('Pending Payment')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_PENDING_PAYMENT))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', ServiceOrder::STATUS_PENDING_PAYMENT)->count())
                ->badgeColor('warning'),
            'processing' => Tab::make('Processing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_PROCESSING)),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_ACTIVE))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', ServiceOrder::STATUS_ACTIVE)->count())
                ->badgeColor('success'),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_COMPLETED)),
        ];
    }
}
