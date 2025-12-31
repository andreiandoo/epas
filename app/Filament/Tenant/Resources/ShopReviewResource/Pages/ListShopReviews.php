<?php

namespace App\Filament\Tenant\Resources\ShopReviewResource\Pages;

use App\Filament\Tenant\Resources\ShopReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListShopReviews extends ListRecords
{
    protected static string $resource = ShopReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
