<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class Domains extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Domains';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.tenant.pages.domains';

    public function getTitle(): string
    {
        return 'Domains';
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
