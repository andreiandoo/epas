<?php

namespace App\Filament\Marketplace\Resources\OrderInsuranceResource\Pages;

use App\Filament\Marketplace\Resources\OrderInsuranceResource;
use App\Filament\Marketplace\Resources\OrderResource\Pages\ListOrders;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListOrderInsurance extends ListOrders
{
    protected static string $resource = OrderInsuranceResource::class;

    public function getHeading(): string|Htmlable
    {
        return $this->listHeading(__('Comenzi cu asigurare bilete'));
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('<div style="font-size:0.875rem;color:#6b7280;">Comenzi în care clientul a cumpărat asigurare (bilete rambursabile). Suma asigurării revine asigurătorului, nu organizatorului.</div>');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
