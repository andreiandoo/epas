<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Concerns\HasEventImport;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\EventResource;
use App\Models\MarketplaceOrganizer;
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
            // Try allowed_tenants first
            $allowedTenants = $client->allowed_tenants ?? [];
            if (!empty($allowedTenants)) {
                $firstTenantId = is_array($allowedTenants) ? ($allowedTenants[0] ?? null) : null;
                if ($firstTenantId) return (int) $firstTenantId;
            }
        }

        return Tenant::first()?->id;
    }

    protected function getForms(): array
    {
        return [
            'eventSetupForm',
        ];
    }

    protected function getExtraEventFormFields(): array
    {
        $marketplace = static::getMarketplaceClient();

        return [
            Forms\Components\Select::make('marketplace_organizer_id')
                ->label('Organizator')
                ->searchable()
                ->preload()
                ->options(function () use ($marketplace) {
                    if (!$marketplace) return [];
                    return MarketplaceOrganizer::where('marketplace_client_id', $marketplace->id)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($o) => [$o->id => ($o->company_name ?? $o->name ?? 'Organizator #' . $o->id)])
                        ->toArray();
                })
                ->required(),

            Forms\Components\Hidden::make('tenant_id')
                ->default(fn () => $this->resolveImportTenantId()),
        ];
    }
}
