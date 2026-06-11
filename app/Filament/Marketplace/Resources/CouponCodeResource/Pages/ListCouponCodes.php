<?php

namespace App\Filament\Marketplace\Resources\CouponCodeResource\Pages;

use App\Filament\Marketplace\Resources\CouponCodeResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Coupon\CouponCode;
use App\Models\MarketplaceOrganizerPromoCode;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;

class ListCouponCodes extends ListRecords
{
    use HasMarketplaceContext;

    protected static string $resource = CouponCodeResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->autoExpireAndExhaustCodes();
        $this->syncOrganizerCodesToCouponCodes();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Auto-expire codes past their expiry date and exhaust codes that reached usage limit.
     */
    protected function autoExpireAndExhaustCodes(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return;
        }

        // Expire codes past their expiry date
        CouponCode::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        // Exhaust codes that reached usage limit
        CouponCode::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'active')
            ->whereNotNull('max_uses_total')
            ->whereColumn('current_uses', '>=', 'max_uses_total')
            ->update(['status' => 'exhausted']);

        // Also update organizer promo codes (mkt_promo_codes)
        MarketplaceOrganizerPromoCode::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        MarketplaceOrganizerPromoCode::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'active')
            ->whereNotNull('usage_limit')
            ->whereColumn('usage_count', '>=', 'usage_limit')
            ->update(['status' => 'exhausted']);
    }

    /**
     * Sync organizer promo codes (mkt_promo_codes) to coupon_codes table
     * so they appear in this listing. Only creates missing mirrors.
     */
    protected function syncOrganizerCodesToCouponCodes(): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return;
        }

        try {
            // Get all organizer promo codes for this marketplace
            $orgCodes = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $marketplace->id)->get();

            if ($orgCodes->isEmpty()) {
                return;
            }

            // Get existing coupon codes to avoid duplicates
            $existingCodes = CouponCode::where('marketplace_client_id', $marketplace->id)
                ->pluck('code')
                ->map(fn ($c) => strtoupper($c))
                ->toArray();

            $synced = 0;
            foreach ($orgCodes as $orgCode) {
                if (in_array(strtoupper($orgCode->code), $existingCodes)) {
                    continue;
                }

                CouponCode::create([
                    'marketplace_client_id' => $marketplace->id,
                    'code' => $orgCode->code,
                    'discount_type' => $orgCode->type === 'percentage' ? 'percentage' : 'fixed_amount',
                    'discount_value' => $orgCode->value,
                    'max_discount_amount' => $orgCode->max_discount_amount,
                    'min_purchase_amount' => $orgCode->min_purchase_amount,
                    'min_quantity' => $orgCode->min_tickets,
                    'max_uses_total' => $orgCode->usage_limit,
                    'max_uses_per_user' => $orgCode->usage_limit_per_customer,
                    'current_uses' => $orgCode->usage_count ?? 0,
                    'starts_at' => $orgCode->starts_at,
                    'expires_at' => $orgCode->expires_at,
                    'status' => $orgCode->status ?? 'active',
                    'is_public' => $orgCode->is_public ?? false,
                    'source' => 'organizer',
                    'applicable_events' => $orgCode->marketplace_event_id
                        ? [(int) $orgCode->marketplace_event_id]
                        : null,
                    'applicable_ticket_types' => $orgCode->ticket_type_id
                        ? [(int) $orgCode->ticket_type_id]
                        : null,
                ]);
                $synced++;
            }

            if ($synced > 0) {
                Log::info("Synced {$synced} organizer promo codes to coupon_codes", [
                    'marketplace_client_id' => $marketplace->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to sync organizer promo codes to coupon_codes', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
