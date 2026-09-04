<?php

namespace App\Filament\Marketplace\Resources\OrderCulturalCardResource\Pages;

use App\Filament\Marketplace\Resources\OrderCulturalCardResource;
use App\Filament\Marketplace\Resources\OrderResource\Pages\ListOrders;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListOrderCulturalCard extends ListOrders
{
    protected static string $resource = OrderCulturalCardResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Comenzi cu Card Cultural Național <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('<div style="font-size:0.875rem;color:#6b7280;">Comenzi plătite via card cultural. Surcharge-ul Netopia (4%) revine AmBilet, nu organizatorului — AmBilet îl transferă mai departe către furnizorul de plată card cultural.</div>');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
