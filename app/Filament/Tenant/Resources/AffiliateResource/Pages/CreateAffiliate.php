<?php

namespace App\Filament\Tenant\Resources\AffiliateResource\Pages;

use App\Filament\Tenant\Resources\AffiliateResource;
use App\Models\AffiliateCoupon;
use Filament\Resources\Pages\CreateRecord;

class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;

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
