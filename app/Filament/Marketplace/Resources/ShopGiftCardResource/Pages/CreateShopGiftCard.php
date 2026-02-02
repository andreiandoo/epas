<?php

namespace App\Filament\Marketplace\Resources\ShopGiftCardResource\Pages;

use App\Filament\Marketplace\Resources\ShopGiftCardResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateShopGiftCard extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ShopGiftCardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;

        // Set current balance to initial balance
        if (!isset($data['current_balance_cents'])) {
            $data['current_balance_cents'] = $data['initial_balance_cents'];
        }

        return $data;
    }
}
