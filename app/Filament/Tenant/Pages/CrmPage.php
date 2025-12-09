<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Pages\Page;

class CrmPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'CRM';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 8;
    protected static ?string $slug = 'crm';
    protected string $view = 'filament.tenant.pages.crm';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('microservices.slug', 'crm')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function getTitle(): string
    {
        return 'Customer Relationship Management';
    }
}
