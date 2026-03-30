<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\HasEventImport;
use App\Filament\Resources\Events\EventResource;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;

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
     * Admin panel: tenant_id comes from a select in the form or defaults to first tenant.
     */
    protected function resolveImportTenantId(): ?int
    {
        // Admin can choose tenant — use form data or default
        $tenantId = $this->eventFormData['tenant_id'] ?? null;
        if ($tenantId) {
            return (int) $tenantId;
        }

        // Fallback: user's tenant_id or first tenant
        return auth()->user()->tenant_id ?? Tenant::first()?->id;
    }

    public function eventSetupForm(Schema $schema): Schema
    {
        $parentSchema = parent::eventSetupForm($schema);

        // Prepend tenant selector for admin panel
        $existingSchema = $parentSchema->getComponents();

        $tenantSelect = Forms\Components\Select::make('tenant_id')
            ->label('Tenant')
            ->searchable()
            ->preload()
            ->options(Tenant::pluck('public_name', 'id'))
            ->required()
            ->default(auth()->user()->tenant_id ?? Tenant::first()?->id);

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
