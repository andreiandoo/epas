<?php

namespace App\Filament\Marketplace\Resources\ShopReviewResource\Pages;

use App\Filament\Marketplace\Resources\ShopReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListShopReviews extends ListRecords
{
    protected static string $resource = ShopReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
