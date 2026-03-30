<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Concerns\HasEventImport;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\EventResource;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Resources\Pages\Page;

class ImportEvents extends Page implements HasForms
{
    use InteractsWithForms;
    use HasEventImport;
    use HasMarketplaceContext;

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

        $client = static::getMarketplaceClient();
        if ($client) {
            $tenant = Tenant::where('marketplace_client_id', $client->id)->first();
            if ($tenant) {
                return $tenant->id;
            }
        }

        return Tenant::first()?->id;
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
                ->default(fn () => $this->resolveImportTenantId()),
        ];
    }
}
