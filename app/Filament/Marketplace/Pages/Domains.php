<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class Domains extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Domains';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected static bool $shouldRegisterNavigation = false; // Moved to Settings tab
    protected string $view = 'filament.marketplace.pages.domains';

    public function getTitle(): string
    {
        return 'Domains';
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return ['domains' => collect()];
        }

        $domains = $marketplace->domains()->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc')->get();

        return [
            'domains' => $domains,
        ];
    }
}
