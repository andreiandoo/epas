<?php

namespace App\Filament\Marketplace\Resources\AffiliateResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateResource;
use App\Models\AffiliateCoupon;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateAffiliate extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()_id;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Create coupon if provided
        $couponCode = $this->data['coupon_code'] ?? null;

        if ($couponCode) {
            AffiliateCoupon::create([
                'affiliate_id' => $this->record->id,
                'coupon_code' => strtoupper($couponCode),
                'active' => true,
            ]);
        }
    }
}
