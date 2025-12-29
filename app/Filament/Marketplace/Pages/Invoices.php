<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class Invoices extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoices';
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.marketplace.pages.invoices';

    public function getTitle(): string
    {
        return 'Invoices';
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return ['invoices' => collect()];
        }

        $invoices = $marketplace->invoices()->orderBy('issue_date', 'desc')->get();

        return [
            'invoices' => $invoices,
        ];
    }
}
