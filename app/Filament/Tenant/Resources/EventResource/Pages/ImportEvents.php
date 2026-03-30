<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Concerns\HasEventImport;
use App\Filament\Tenant\Resources\EventResource;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Resources\Pages\Page;

class ImportEvents extends Page implements HasForms
{
    use InteractsWithForms;
    use HasEventImport;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Import Evenimente';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Import Events';

    protected string $view = 'filament.pages.import-events';

    /**
     * Tenant panel: tenant_id from authenticated user.
     */
    protected function resolveImportTenantId(): ?int
    {
        return auth()->user()->tenant_id ?? auth()->user()->tenant?->id;
    }

    protected function getForms(): array
    {
        return [
            'eventSetupForm',
        ];
    }
}
