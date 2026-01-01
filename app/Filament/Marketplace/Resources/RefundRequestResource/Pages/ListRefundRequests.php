<?php

namespace App\Filament\Marketplace\Resources\RefundRequestResource\Pages;

use App\Filament\Marketplace\Resources\RefundRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRefundRequests extends ListRecords
{
    protected static string $resource = RefundRequestResource::class;

    public function getTabs(): array
    {
        return [
            'needs_action' => Tab::make('Needs Action')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'under_review', 'approved']))
                ->badge(fn () => $this->getModel()::query()
                    ->where('marketplace_client_id', RefundRequestResource::getMarketplaceClient()?->id)
                    ->whereIn('status', ['pending', 'under_review', 'approved'])
                    ->count()),
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
