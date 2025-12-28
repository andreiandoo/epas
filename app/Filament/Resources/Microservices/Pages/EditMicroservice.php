<?php

namespace App\Filament\Resources\Microservices\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use Filament\Resources\Pages\EditRecord;

class EditMicroservice extends EditRecord
{
    protected static string $resource = MicroserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('viewTenants')
                ->label('View Tenants')
                ->icon('heroicon-o-users')
                ->url(fn () => static::getResource()::getUrl('tenants', ['record' => $this->record])),
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
