<?php

namespace App\Filament\Marketplace\Resources\GiftCardResource\Pages;

use App\Filament\Marketplace\Resources\GiftCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCard extends CreateRecord
{
    protected static string $resource = GiftCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
