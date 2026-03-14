<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListMarketplaceCustomers extends ListRecords
{
    protected static string $resource = MarketplaceCustomerResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Clienți <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
