<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Microservices extends Page
{
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
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return ['microservices' => collect()];
        }

        $microservices = $tenant->microservices()
            ->wherePivot('is_active', true)
            ->orderByPivot('activated_at', 'desc')
            ->get();

        return [
            'microservices' => $microservices,
        ];
    }
}
