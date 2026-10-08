<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Domains extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Domains';

    public static function getNavigationLabel(): string
    {
        return __('Domains');
    }
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Settings');
    }
    protected static ?int $navigationSort = 2;
    protected static bool $shouldRegisterNavigation = false; // Moved to Settings tab
    protected string $view = 'filament.tenant.pages.domains';

    public function getTitle(): string
    {
        return __('Domains');
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return ['domains' => collect()];
        }

        $domains = $tenant->domains()->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc')->get();

        return [
            'domains' => $domains,
        ];
    }
}
