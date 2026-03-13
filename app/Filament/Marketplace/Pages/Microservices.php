<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class Microservices extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationLabel = 'Microservices';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 0;
    protected string $view = 'filament.marketplace.pages.microservices';

    public function getTitle(): string
    {
        return 'Microservices';
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return ['microservices' => collect()];
        }

        $microservices = $marketplace->microservices()
            ->wherePivot('is_active', true)
            ->orderByPivot('activated_at', 'desc')
            ->get();

        return [
            'microservices' => $microservices,
        ];
    }
}
