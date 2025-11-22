<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\PlatformCredentialResource;
use Filament\Resources\Pages\EditRecord;

class EditPlatformCredential extends EditRecord
{
    protected static string $resource = PlatformCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
