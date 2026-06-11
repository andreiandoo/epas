<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\HasEventImport;
use App\Filament\Resources\Events\EventResource;
use App\Models\Tenant;
use Filament\Forms;
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

    protected function resolveImportTenantId(): ?int
    {
        $tenantId = $this->eventFormData['tenant_id'] ?? null;
        if ($tenantId) {
            return (int) $tenantId;
        }

        return auth()->user()->tenant_id ?? Tenant::first()?->id;
    }

    protected function getForms(): array
    {
        return [
            'eventSetupForm',
        ];
    }

    protected function getExtraEventFormFields(): array
    {
        return [
            Forms\Components\Select::make('tenant_id')
                ->label('Tenant')
                ->searchable()
                ->preload()
                ->options(Tenant::pluck('public_name', 'id'))
                ->required()
                ->default(auth()->user()->tenant_id ?? Tenant::first()?->id),
        ];
    }
}
