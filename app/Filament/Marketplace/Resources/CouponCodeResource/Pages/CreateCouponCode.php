<?php

namespace App\Filament\Marketplace\Resources\CouponCodeResource\Pages;

use App\Filament\Marketplace\Resources\CouponCodeResource;
use App\Models\MarketplaceOrganizerPromoCode;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Facades\Log;

class CreateCouponCode extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = CouponCodeResource::class;

    public function mount(): void
    {
        parent::mount();

        // Pre-fill from query parameters (e.g. when coming from event edit page)
        $eventId = request()->query('event_id');
        $organizerId = request()->query('organizer_id');

        if ($eventId || $organizerId) {
            $fillData = [];
            if ($eventId) {
                $fillData['applicable_events'] = [(int) $eventId];
            }
            if ($organizerId) {
                $fillData['marketplace_organizer_id'] = (int) $organizerId;
            }
            $this->form->fill(array_merge($this->form->getRawState(), $fillData));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $data['code'] = strtoupper($data['code']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Mirror to mkt_promo_codes so organizers see it on their promo page
        $coupon = $this->record;
        $applicableEvents = $coupon->applicable_events ?? [];
        $applicableTicketTypes = $coupon->applicable_ticket_types ?? [];

        if (empty($applicableEvents)) {
            return; // No events targeted, no organizer to mirror to
        }

        try {
            // Only consider events that still exist; stale ids in the JSON
            // applicable_events array would otherwise break the FK on insert.
            $validEventIds = \App\Models\MarketplaceEvent::whereIn('id', array_map('intval', $applicableEvents))
                ->pluck('marketplace_organizer_id', 'id');

            if ($validEventIds->isEmpty()) {
                return;
            }

            $organizerId = $validEventIds->first();

            // Determine applies_to
            $appliesTo = 'all_events';
            $eventId = null;
            $ticketTypeId = null;

            $firstValidEventId = (int) $validEventIds->keys()->first();

            if (!empty($applicableTicketTypes)) {
                $appliesTo = 'ticket_type';
                $ticketTypeId = (int) $applicableTicketTypes[0];
                $eventId = $firstValidEventId;
            } elseif (!empty($applicableEvents)) {
                $appliesTo = 'specific_event';
                $eventId = $firstValidEventId;
            }

            // Check if already mirrored
            $exists = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $coupon->marketplace_client_id)
                ->where('code', $coupon->code)
                ->exists();

            if (!$exists) {
                MarketplaceOrganizerPromoCode::create([
                    'marketplace_client_id' => $coupon->marketplace_client_id,
                    'marketplace_organizer_id' => $organizerId,
                    'marketplace_event_id' => $eventId,
                    'code' => $coupon->code,
                    'name' => $coupon->code,
                    'type' => $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed',
                    'value' => $coupon->discount_value,
                    'applies_to' => $appliesTo,
                    'ticket_type_id' => $ticketTypeId,
                    'min_purchase_amount' => $coupon->min_purchase_amount,
                    'max_discount_amount' => $coupon->max_discount_amount,
                    'min_tickets' => $coupon->min_quantity,
                    'usage_limit' => $coupon->max_uses_total,
                    'usage_limit_per_customer' => $coupon->max_uses_per_user,
                    'starts_at' => $coupon->starts_at,
                    'expires_at' => $coupon->expires_at,
                    'is_public' => $coupon->is_public ?? false,
                    'status' => $coupon->status ?? 'active',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to mirror CouponCode to mkt_promo_codes', [
                'code' => $coupon->code,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
