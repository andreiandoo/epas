<?php

namespace App\Filament\Tenant\Resources\ShopGiftCardResource\Pages;

use App\Filament\Tenant\Resources\ShopGiftCardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShopGiftCard extends CreateRecord
{
    protected static string $resource = ShopGiftCardResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;

        // Set current balance to initial balance
        if (!isset($data['current_balance_cents'])) {
            $data['current_balance_cents'] = $data['initial_balance_cents'];
        }

        return $data;
    }
}
