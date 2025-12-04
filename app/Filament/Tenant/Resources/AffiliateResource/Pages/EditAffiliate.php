<?php

namespace App\Filament\Tenant\Resources\AffiliateResource\Pages;

use App\Filament\Tenant\Resources\AffiliateResource;
use App\Models\AffiliateCoupon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Update coupon if provided
        $couponCode = $this->data['coupon_code'] ?? null;
        $existingCoupon = $this->record->coupons()->where('active', true)->first();

        if ($couponCode) {
            if ($existingCoupon) {
                $existingCoupon->update(['coupon_code' => strtoupper($couponCode)]);
            } else {
                AffiliateCoupon::create([
                    'affiliate_id' => $this->record->id,
                    'coupon_code' => strtoupper($couponCode),
                    'active' => true,
                ]);
            }
        } elseif ($existingCoupon) {
            $existingCoupon->update(['active' => false]);
        }
    }
}
