<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListMarketplaceCustomers extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = MarketplaceCustomerResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Clienți'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Client nou'))
                ->icon('heroicon-m-plus'),
        ];
    }

    /** Same conditions as the table filters (status, Account Type, Has Orders, Email Verified). */
    public function getTabs(): array
    {
        $base = fn () => $this->getResource()::getEloquentQuery();

        return [
            'all' => Tab::make(__('Toți'))
                ->badge(fn () => $base()->count())
                ->badgeColor('gray'),
            'registered' => Tab::make(__('Cu cont'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('password'))
                ->badge(fn () => $base()->whereNotNull('password')->count())
                ->badgeColor('success'),
            'guest' => Tab::make(__('Oaspeți'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('password'))
                ->badge(fn () => $base()->whereNull('password')->count())
                ->badgeColor('gray'),
            'with_orders' => Tab::make(__('Cu comenzi'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('total_orders', '>', 0))
                ->badge(fn () => $base()->where('total_orders', '>', 0)->count())
                ->badgeColor('info'),
            'suspended' => Tab::make(__('Suspendați'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended'))
                ->badge(fn () => $base()->where('status', 'suspended')->count())
                ->badgeColor('danger'),
        ];
    }
}
