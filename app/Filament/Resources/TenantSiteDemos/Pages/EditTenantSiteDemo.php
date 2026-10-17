<?php

namespace App\Filament\Resources\TenantSiteDemos\Pages;

use App\Filament\Resources\TenantSiteDemos\TenantSiteDemoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantSiteDemo extends EditRecord
{
    protected static string $resource = TenantSiteDemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('Deschide demo-ul')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->domain ? 'https://' . $this->record->domain->domain : null, true),
            Actions\DeleteAction::make(),
        ];
    }
}
