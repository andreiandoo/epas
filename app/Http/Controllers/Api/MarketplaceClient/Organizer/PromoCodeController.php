<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Coupon\CouponCode;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerPromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PromoCodeController extends BaseController
{
    /**
     * List organizer's promo codes (from both mkt_promo_codes and coupon_codes tables)
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        // 1. Get organizer's own promo codes (mkt_promo_codes)
        $query = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)
            ->with(['event:id,title,slug', 'ticketType:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_id')) {
            $query->where('marketplace_event_id', $request->event_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $ownCodes = $query->get()->map(fn ($code) => $this->formatPromoCode($code));

        // 2. Get CouponCodes (admin- or organizer-mirrored) that apply to this organizer.
        // A code is in scope when EITHER it carries marketplace_organizer_id =
        // $organizer->id, OR its applicable_events list overlaps an event the
        // organizer owns. Both branches must be considered — the second one
        // handles legacy admin-created coupons targeting specific events without
        // an organizer scope, the first one handles coupons mirrored from
        // /organizator/promo and admin coupons explicitly scoped to this organizer.
        $organizerEventIds = \App\Models\Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->pluck('id')
            ->toArray();

        $couponQuery = CouponCode::where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('status', '!=', 'deleted')
            ->where(function ($q) use ($organizerEventIds, $organizer) {
                $q->where('marketplace_organizer_id', $organizer->id);
                foreach ($organizerEventIds as $eventId) {
                    $q->orWhereJsonContains('applicable_events', $eventId)
                      ->orWhereJsonContains('applicable_events', (string) $eventId);
                }
            })
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $couponQuery->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $couponQuery->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%");
            });
        }

        // Exclude codes already in mkt_promo_codes (same code for this client)
        $ownCodesValues = $ownCodes->pluck('code')->toArray();
        if (!empty($ownCodesValues)) {
            $couponQuery->whereNotIn('code', $ownCodesValues);
        }

        $adminCodes = $couponQuery->get()->map(fn ($coupon) => $this->formatCouponCode($coupon, $organizerEventIds));

        // Merge results, own codes first
        $allCodes = $ownCodes->concat($adminCodes)->sortByDesc('created_at')->values();

        return $this->success([
            'data' => $allCodes,
            'total' => $allCodes->count(),
        ]);
    }

    /**
     * Create a new promo code
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'applies_to' => 'required|in:all_events,specific_event,ticket_type',
            'event_id' => 'nullable|integer|exists:events,id',
            'ticket_type_id' => 'nullable|integer|exists:ticket_types,id',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_tickets' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_public' => 'boolean',
        ]);

        // Validate percentage limit
        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return $this->error('Percentage value cannot exceed 100', 422);
        }

        // Validate event ownership if specific event
        if ($validated['applies_to'] === 'specific_event') {
            if (empty($validated['event_id'])) {
                return $this->error('Event ID is required when applying to specific event', 422);
            }

            $eventBelongsToOrganizer = \App\Models\Event::where('id', $validated['event_id'])
                ->where('marketplace_organizer_id', $organizer->id)
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->exists();

            if (!$eventBelongsToOrganizer) {
                return $this->error('Event not found', 404);
            }
        }

        // Validate ticket type ownership if ticket_type
        if ($validated['applies_to'] === 'ticket_type') {
            if (empty($validated['ticket_type_id'])) {
                return $this->error('Ticket type ID is required', 422);
            }

            $ticketTypeBelongsToOrganizer = \App\Models\Event::where('marketplace_organizer_id', $organizer->id)
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->whereHas('ticketTypes', function ($q) use ($validated) {
                    $q->where('id', $validated['ticket_type_id']);
                })
                ->exists();

            if (!$ticketTypeBelongsToOrganizer) {
                return $this->error('Ticket type not found', 404);
            }
        }

        // Check for code uniqueness
        $code = strtoupper($validated['code'] ?? Str::random(8));
        $existingCode = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('code', $code)
            ->exists();

        if ($existingCode) {
            return $this->error('A promo code with this code already exists', 422);
        }

        $promoCode = MarketplaceOrganizerPromoCode::create([
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'marketplace_event_id' => $validated['event_id'] ?? null,
            'code' => $code,
            'name' => $validated['name'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'applies_to' => $validated['applies_to'],
            'ticket_type_id' => $validated['ticket_type_id'] ?? null,
            'min_purchase_amount' => $validated['min_purchase_amount'] ?? null,
            'max_discount_amount' => $validated['max_discount_amount'] ?? null,
            'min_tickets' => $validated['min_tickets'] ?? null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'usage_limit_per_customer' => $validated['usage_limit_per_customer'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'status' => 'active',
        ]);

        // Mirror to coupon_codes table so admin sees it in Coupon Codes list.
        // Always scope to the creating organizer so the code never bleeds onto
        // events of other organizers — even when no specific event is picked.
        try {
            $couponData = [
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'code' => $code,
                'discount_type' => $validated['type'] === 'percentage' ? 'percentage' : 'fixed_amount',
                'discount_value' => $validated['value'],
                'max_discount_amount' => $validated['max_discount_amount'] ?? null,
                'min_purchase_amount' => $validated['min_purchase_amount'] ?? null,
                'min_quantity' => $validated['min_tickets'] ?? null,
                'max_uses_total' => $validated['usage_limit'] ?? null,
                'max_uses_per_user' => $validated['usage_limit_per_customer'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'status' => 'active',
                'is_public' => $validated['is_public'] ?? false,
                'source' => 'organizer',
            ];

            // Set applicable_events / applicable_ticket_types based on applies_to
            if (!empty($validated['event_id'])) {
                $couponData['applicable_events'] = [(int) $validated['event_id']];
            }
            if (!empty($validated['ticket_type_id'])) {
                $couponData['applicable_ticket_types'] = [(int) $validated['ticket_type_id']];
            }

            CouponCode::create($couponData);
        } catch (\Throwable $e) {
            // Don't fail the main operation if mirror fails
            \Illuminate\Support\Facades\Log::warning('Failed to mirror promo code to coupon_codes', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success([
            'promo_code' => $this->formatPromoCode($promoCode->load(['event:id,title,slug', 'ticketType:id,name'])),
        ], 'Promo code created successfully', 201);
    }

    /**
     * Get a single promo code
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)
            ->with(['event:id,title,slug', 'ticketType:id,name'])
            ->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        return $this->success([
            'promo_code' => $this->formatPromoCode($promoCode),
        ]);
    }

    /**
     * Update a promo code
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_tickets' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'is_public' => 'boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        // Validate usage_limit is not less than current usage
        if (isset($validated['usage_limit']) && $validated['usage_limit'] < $promoCode->usage_count) {
            return $this->error('Usage limit cannot be less than current usage count', 422);
        }

        $promoCode->update($validated);

        return $this->success([
            'promo_code' => $this->formatPromoCode($promoCode->fresh(['event:id,title,slug', 'ticketType:id,name'])),
        ], 'Promo code updated successfully');
    }

    /**
     * Deactivate a promo code
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        $promoCode->deactivate();

        return $this->success(null, 'Promo code deactivated successfully');
    }

    /**
     * Activate a promo code
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        if ($promoCode->isExpired()) {
            return $this->error('Cannot activate an expired promo code', 400);
        }

        if ($promoCode->isExhausted()) {
            return $this->error('Cannot activate an exhausted promo code. Increase usage limit first.', 400);
        }

        $promoCode->activate();

        return $this->success(null, 'Promo code activated successfully');
    }

    /**
     * Delete a promo code
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        // Soft delete
        $promoCode->delete();

        // Mirror deletion to coupon_codes so the row also disappears from the
        // organizer's list (index() falls back to coupon_codes for any code not
        // present in mkt_promo_codes — without this, the deleted code would
        // re-surface tagged as "Admin" on refresh).
        try {
            CouponCode::where('marketplace_client_id', $organizer->marketplace_client_id)
                ->where('marketplace_organizer_id', $organizer->id)
                ->where('code', $promoCode->code)
                ->get()
                ->each(function ($coupon) {
                    $coupon->update(['status' => 'deleted']);
                    $coupon->delete();
                });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to delete mirrored coupon_codes row', [
                'code' => $promoCode->code,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->success(null, 'Promo code deleted successfully');
    }

    /**
     * Get usage statistics for a promo code
     */
    public function stats(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)
            ->withCount('usage')
            ->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        $usage = $promoCode->usage();

        $stats = [
            'total_uses' => $promoCode->usage_count,
            'usage_limit' => $promoCode->usage_limit,
            'remaining_uses' => $promoCode->usage_limit
                ? max(0, $promoCode->usage_limit - $promoCode->usage_count)
                : null,
            'total_discount_given' => (float) $usage->sum('discount_applied'),
            'total_order_value' => (float) $usage->sum('order_total'),
            'unique_customers' => $usage->distinct('customer_email')->count('customer_email'),
            'average_discount' => $promoCode->usage_count > 0
                ? round((float) $usage->avg('discount_applied'), 2)
                : 0,
            'average_order_value' => $promoCode->usage_count > 0
                ? round((float) $usage->avg('order_total'), 2)
                : 0,
        ];

        return $this->success(['stats' => $stats]);
    }

    /**
     * Get usage history for a promo code
     */
    public function usageHistory(Request $request, int $id): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $promoCode = MarketplaceOrganizerPromoCode::forOrganizer($organizer->id)->find($id);

        if (!$promoCode) {
            return $this->error('Promo code not found', 404);
        }

        $query = $promoCode->usage()
            ->with(['order:id,order_number,total,created_at', 'customer:id,first_name,last_name,email'])
            ->orderBy('created_at', 'desc');

        $perPage = min((int) $request->input('per_page', 20), 100);
        $usage = $query->paginate($perPage);

        return $this->paginated($usage, function ($item) {
            return [
                'id' => $item->id,
                'customer_email' => $item->customer_email,
                'customer_name' => $item->customer
                    ? "{$item->customer->first_name} {$item->customer->last_name}"
                    : null,
                'discount_applied' => (float) $item->discount_applied,
                'order_total' => (float) $item->order_total,
                'order' => $item->order ? [
                    'id' => $item->order->id,
                    'order_number' => $item->order->order_number,
                    'total' => (float) $item->order->total,
                ] : null,
                'used_at' => $item->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Bulk create promo codes
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            return $this->error('Unauthorized', 401);
        }

        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:100',
            'prefix' => 'nullable|string|max:10',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'applies_to' => 'required|in:all_events,specific_event',
            'event_id' => 'nullable|integer|exists:events,id',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return $this->error('Percentage value cannot exceed 100', 422);
        }

        // Validate event ownership
        if ($validated['applies_to'] === 'specific_event' && !empty($validated['event_id'])) {
            $eventBelongsToOrganizer = \App\Models\Event::where('id', $validated['event_id'])
                ->where('marketplace_organizer_id', $organizer->id)
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->exists();

            if (!$eventBelongsToOrganizer) {
                return $this->error('Event not found', 404);
            }
        }

        $prefix = strtoupper($validated['prefix'] ?? '');
        $codes = [];

        for ($i = 0; $i < $validated['count']; $i++) {
            $code = $prefix . strtoupper(Str::random(8 - strlen($prefix)));

            // Ensure uniqueness
            $attempts = 0;
            while (MarketplaceOrganizerPromoCode::where('marketplace_client_id', $organizer->marketplace_client_id)
                ->where('code', $code)
                ->exists() && $attempts < 10) {
                $code = $prefix . strtoupper(Str::random(8 - strlen($prefix)));
                $attempts++;
            }

            $promoCode = MarketplaceOrganizerPromoCode::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'marketplace_event_id' => $validated['event_id'] ?? null,
                'code' => $code,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'applies_to' => $validated['applies_to'],
                'usage_limit' => $validated['usage_limit'] ?? 1, // Default single use for bulk
                'usage_limit_per_customer' => $validated['usage_limit_per_customer'] ?? 1,
                'starts_at' => $validated['starts_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'status' => 'active',
            ]);

            $codes[] = $this->formatPromoCode($promoCode);
        }

        return $this->success([
            'promo_codes' => $codes,
            'count' => count($codes),
        ], count($codes) . ' promo codes created successfully', 201);
    }

    /**
     * Format promo code for response
     */
    protected function formatPromoCode(MarketplaceOrganizerPromoCode $promoCode): array
    {
        return [
            'id' => $promoCode->id,
            'code' => $promoCode->code,
            'name' => $promoCode->name,
            'description' => $promoCode->description,
            'type' => $promoCode->type,
            'value' => (float) $promoCode->value,
            'formatted_discount' => $promoCode->getFormattedDiscount(),
            'applies_to' => $promoCode->applies_to,
            'event' => $promoCode->event ? [
                'id' => $promoCode->event->id,
                'name' => $this->resolveEventTitle($promoCode->event),
                'slug' => $promoCode->event->slug,
            ] : null,
            'ticket_type' => $promoCode->ticketType ? [
                'id' => $promoCode->ticketType->id,
                'name' => $promoCode->ticketType->name,
            ] : null,
            'min_purchase_amount' => $promoCode->min_purchase_amount ? (float) $promoCode->min_purchase_amount : null,
            'max_discount_amount' => $promoCode->max_discount_amount ? (float) $promoCode->max_discount_amount : null,
            'min_tickets' => $promoCode->min_tickets,
            'usage_limit' => $promoCode->usage_limit,
            'usage_limit_per_customer' => $promoCode->usage_limit_per_customer,
            'usage_count' => $promoCode->usage_count,
            'remaining_uses' => $promoCode->usage_limit
                ? max(0, $promoCode->usage_limit - $promoCode->usage_count)
                : null,
            'starts_at' => $promoCode->starts_at?->toIso8601String(),
            'expires_at' => $promoCode->expires_at?->toIso8601String(),
            'status' => $promoCode->status,
            'is_public' => $promoCode->is_public,
            'is_valid' => $promoCode->isValid(),
            'source' => 'organizer',
            'created_at' => $promoCode->created_at->toIso8601String(),
        ];
    }

    /**
     * Resolve translatable Event title to a single string for API responses.
     */
    protected function resolveEventTitle(\App\Models\Event $event): string
    {
        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? (reset($title) ?: '');
        }
        return (string) ($title ?? '');
    }

    /**
     * Format a CouponCode (admin-created) in the same shape as organizer promo codes
     */
    protected function formatCouponCode(CouponCode $coupon, array $organizerEventIds): array
    {
        // Determine applies_to from coupon structure
        $appliesTo = 'all_events';
        $eventData = null;
        $ticketTypeData = null;

        if (!empty($coupon->applicable_ticket_types)) {
            $appliesTo = 'ticket_type';
            $firstTtId = (int) $coupon->applicable_ticket_types[0];
            $tt = \App\Models\TicketType::find($firstTtId);
            if ($tt) {
                $ticketTypeData = ['id' => $tt->id, 'name' => $tt->name];
            }
        }

        if (!empty($coupon->applicable_events)) {
            if ($appliesTo !== 'ticket_type') {
                $appliesTo = 'specific_event';
            }
            $firstEventId = (int) $coupon->applicable_events[0];
            $event = \App\Models\Event::find($firstEventId);
            if ($event) {
                $title = $event->title;
                $eventName = is_array($title) ? ($title['ro'] ?? $title['en'] ?? reset($title) ?: '') : ($title ?? '');
                $eventData = ['id' => $event->id, 'name' => $eventName, 'slug' => $event->slug];
            }
        }

        $type = $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed';
        $value = (float) $coupon->discount_value;
        $formattedDiscount = $type === 'percentage'
            ? "{$value}%"
            : number_format($value, 2) . ' RON';

        return [
            'id' => 'coupon_' . $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->code,
            'description' => null,
            'type' => $type,
            'value' => $value,
            'formatted_discount' => $formattedDiscount,
            'applies_to' => $appliesTo,
            'event' => $eventData,
            'ticket_type' => $ticketTypeData,
            'min_purchase_amount' => $coupon->min_purchase_amount ? (float) $coupon->min_purchase_amount : null,
            'max_discount_amount' => $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null,
            'min_tickets' => $coupon->min_quantity,
            'usage_limit' => $coupon->max_uses_total,
            'usage_limit_per_customer' => $coupon->max_uses_per_user,
            'usage_count' => $coupon->current_uses ?? 0,
            'remaining_uses' => $coupon->max_uses_total
                ? max(0, $coupon->max_uses_total - ($coupon->current_uses ?? 0))
                : null,
            'starts_at' => $coupon->starts_at?->toIso8601String(),
            'expires_at' => $coupon->expires_at?->toIso8601String(),
            'status' => $coupon->status,
            'is_public' => $coupon->is_public ?? false,
            'is_valid' => $coupon->isValid(),
            'source' => 'admin',
            'readonly' => true,
            'created_at' => $coupon->created_at->toIso8601String(),
        ];
    }
}
