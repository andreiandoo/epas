<?php

namespace App\Filament\Marketplace\Resources\ServiceOrderResource\Pages;

use App\Filament\Marketplace\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListServiceOrders extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    protected static string $resource = ServiceOrderResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Comenzi servicii'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('Toate'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->count()),
            'pending_payment' => Tab::make(__('Așteaptă plata'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_PENDING_PAYMENT))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', ServiceOrder::STATUS_PENDING_PAYMENT)->count())
                ->badgeColor('warning'),
            'processing' => Tab::make(__('În procesare'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_PROCESSING))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', ServiceOrder::STATUS_PROCESSING)->count()),
            'active' => Tab::make(__('Active'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_ACTIVE))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', ServiceOrder::STATUS_ACTIVE)->count())
                ->badgeColor('success'),
            'completed' => Tab::make(__('Finalizate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrder::STATUS_COMPLETED))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', ServiceOrder::STATUS_COMPLETED)->count()),
        ];
    }
}
