<?php

namespace App\Filament\Tenant\Resources\AffiliateConversionResource\Pages;

use App\Filament\Tenant\Resources\AffiliateConversionResource;
use App\Services\AffiliateTrackingService;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAffiliateConversions extends ListRecords
{
    protected static string $resource = AffiliateConversionResource::class;

    public function getTabs(): array
    {
        $tenant = auth()->user()->tenant;
        $stats = app(AffiliateTrackingService::class)->getTenantStats($tenant->id);

        return [
            'all' => Tab::make('All')
                ->badge($stats['total_conversions']),

            'pending' => Tab::make('Pending')
                ->badge($stats['pending_conversions'])
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),

            'approved' => Tab::make('Approved')
                ->badge($stats['approved_conversions'])
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),

            'reversed' => Tab::make('Reversed')
                ->badge($stats['reversed_conversions'])
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'reversed')),
        ];
    }
}
