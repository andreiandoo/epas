<?php

namespace App\Filament\Marketplace\Resources\GiftCardDesignResource\Pages;

use App\Filament\Marketplace\Resources\GiftCardDesignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGiftCardDesign extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = GiftCardDesignResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
