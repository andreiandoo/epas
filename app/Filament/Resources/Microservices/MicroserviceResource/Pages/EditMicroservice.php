<?php

namespace App\Filament\Resources\Microservices\MicroserviceResource\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMicroservice extends EditRecord
{
    protected static string $resource = MicroserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewTenants')
                ->label('View Tenants')
                ->icon('heroicon-o-users')
                ->url(fn () => MicroserviceResource::getUrl('tenants', ['record' => $this->record])),
            Actions\DeleteAction::make()
                ->label('Delete Microservice')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation(),
        ];
    }
}
