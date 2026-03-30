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
use Filament\Schemas\Schema;

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

    /**
     * Marketplace panel: resolve tenant via marketplace client's associated tenant,
     * or allow selection.
     */
    protected function resolveImportTenantId(): ?int
    {
        // Check form data first
        $tenantId = $this->eventFormData['tenant_id'] ?? null;
        if ($tenantId) {
            return (int) $tenantId;
        }

        // Try to get from marketplace client
        $client = static::getMarketplaceClient();
        if ($client) {
            // Find a tenant associated with this marketplace client
            $tenant = Tenant::where('marketplace_client_id', $client->id)->first();
            if ($tenant) {
                return $tenant->id;
            }
        }

        return Tenant::first()?->id;
    }

    public function eventSetupForm(Schema $schema): Schema
    {
        $parentSchema = parent::eventSetupForm($schema);

        // Prepend tenant selector for marketplace panel
        $existingSchema = $parentSchema->getComponents();

        $tenantSelect = Forms\Components\Select::make('tenant_id')
            ->label('Tenant')
            ->searchable()
            ->preload()
            ->options(Tenant::pluck('public_name', 'id'))
            ->required()
            ->default(fn () => $this->resolveImportTenantId());

        array_unshift($existingSchema, $tenantSelect);

        return $schema->statePath('eventFormData')->schema($existingSchema);
    }

    protected function getForms(): array
    {
        return [
            'eventSetupForm',
        ];
    }
}
