<?php

namespace App\Filament\Marketplace\Resources\GiftCardDesignResource\Pages;

use App\Filament\Marketplace\Resources\GiftCardDesignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGiftCardDesign extends EditRecord
{
    protected static string $resource = GiftCardDesignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
