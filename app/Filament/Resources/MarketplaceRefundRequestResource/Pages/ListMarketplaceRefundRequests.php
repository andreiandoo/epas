<?php

namespace App\Filament\Resources\MarketplaceRefundRequestResource\Pages;

use App\Filament\Resources\MarketplaceRefundRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMarketplaceRefundRequests extends ListRecords
{
    protected static string $resource = MarketplaceRefundRequestResource::class;

    public function getTabs(): array
    {
        return [
            'needs_action' => Tab::make('Needs Action')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'under_review', 'approved']))
                ->badge(fn () => $this->getModel()::whereIn('status', ['pending', 'under_review', 'approved'])->count())
                ->badgeColor('warning'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['refunded', 'partially_refunded'])),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')),
            'all' => Tab::make('All'),
        ];
    }
}
