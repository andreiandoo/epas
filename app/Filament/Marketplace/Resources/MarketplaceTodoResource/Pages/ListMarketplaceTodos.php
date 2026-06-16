<?php

namespace App\Filament\Marketplace\Resources\MarketplaceTodoResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceTodoResource;
use App\Models\MarketplaceTodo;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListMarketplaceTodos extends ListRecords
{
    protected static string $resource = MarketplaceTodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('TODO nou')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => {
                    const toolbar = document.querySelector('.fi-ta-header-toolbar');
                    if (!toolbar) return;
                    const nav = \$el.querySelector('.fi-tabs');
                    if (!nav) return;
                    nav.style.order = '-1';
                    toolbar.prepend(nav);
                })",
            ]);
    }

    public function getTabs(): array
    {
        $adminId = Auth::guard('marketplace_admin')->id();
        $closedStatuses = [
            MarketplaceTodo::STATUS_RESOLVED,
            MarketplaceTodo::STATUS_CLOSED,
        ];

        return [
            'all' => Tab::make('Toate'),

            'open' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', $closedStatuses))
                ->badge(fn () => MarketplaceTodoResource::getEloquentQuery()
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('warning'),

            'mine_open' => Tab::make('Create de mine (active)')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('created_by_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses))
                ->badge(fn () => MarketplaceTodoResource::getEloquentQuery()
                    ->where('created_by_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('primary'),

            'assigned_to_me' => Tab::make('Asignate mie')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses))
                ->badge(fn () => MarketplaceTodoResource::getEloquentQuery()
                    ->where('assigned_to_marketplace_admin_id', $adminId)
                    ->whereNotIn('status', $closedStatuses)
                    ->count() ?: null)
                ->badgeColor('info'),

            'closed' => Tab::make('Închise')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $closedStatuses)),
        ];
    }
}
