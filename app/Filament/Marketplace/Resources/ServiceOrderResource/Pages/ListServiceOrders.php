<?php

namespace App\Filament\Marketplace\Resources\ServiceOrderResource\Pages;

use App\Filament\Marketplace\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListServiceOrders extends ListRecords
{
    protected static string $resource = ServiceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
