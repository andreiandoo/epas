<?php

namespace App\Filament\Marketplace\Resources\ShopAttributeResource\Pages;

use App\Filament\Marketplace\Resources\ShopAttributeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShopAttribute extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ShopAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
