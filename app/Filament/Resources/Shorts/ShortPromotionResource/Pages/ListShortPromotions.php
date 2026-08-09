<?php

namespace App\Filament\Resources\Shorts\ShortPromotionResource\Pages;

use App\Filament\Resources\Shorts\ShortPromotionResource;
use App\Models\ShortPromotion;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListShortPromotions extends ListRecords
{
    protected static string $resource = ShortPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New campaign'),
        ];
    }

    /**
     * Review queue first. Anything waiting on us is one click away; everything
     * else is a tab you have to choose.
     */
    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ShortPromotion::STATUS_PENDING)),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ShortPromotion::STATUS_ACTIVE)),
            'all' => Tab::make('All'),
        ];
    }
}
