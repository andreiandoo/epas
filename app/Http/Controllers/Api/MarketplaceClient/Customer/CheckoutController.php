<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Coupon\CouponCode;
use App\Models\MarketplaceCart;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizerPromoCode;
use App\Models\MarketplaceTicketType;
use App\Models\MarketplacePromoCode;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\ActivityVariant;
use App\Services\Activities\SlotResolver;
use App\Services\Seating\SeatHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends BaseController
{
    public function __construct(
        protected SeatHoldService $seatHoldService
    ) {}
    /**
     * Create order from cart (checkout)
     */
    public function checkout(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'customer' => 'required|array',
            'customer.email' => 'required|email|max:255',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'required|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
            'customer.password' => 'nullable|string|min:8', // Optional account creation
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.name' => 'required_with:beneficiaries|string|max:255',
            'beneficiaries.*.email' => 'nullable|email|max:255',
            'items' => 'nullable|array', // Accept items directly from frontend
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'payment_method' => 'nullable|string|in:card,card_cultural,cash,transfer',
            'accept_terms' => 'required|accepted',
            'promo_code' => 'nullable|string|max:50',
            'ticket_insurance' => 'nullable|boolean',
            'ticket_insurance_amount' => 'nullable|numeric|min:0',
            'cultural_card_surcharge' => 'nullable|numeric|min:0',
            'preview_token' => 'nullable|string',
        ]);

        // Determine if this is a test order via valid preview token
        $isTestOrder = false;
        $previewToken = $request->input('preview_token');
        if ($previewToken) {
            // Validate token against at least the first event in the items
            $firstEventId = $request->input('items.0.event.id') ?? $request->input('items.0.event_id');
            if ($firstEventId && $this->validatePreviewToken($previewToken, (int) $firstEventId)) {
                $isTestOrder = true;
            }
        }

        // Get ticket insurance data
        $hasInsurance = $request->boolean('ticket_insurance');
        $insuranceAmount = $hasInsurance ? (float) $request->input('ticket_insurance_amount', 0) : 0;
        $insurancePerTicket = 0; // Will be calculated after we know total ticket count

        // Try to get items from request body first (frontend sends localStorage cart)
        $requestItems = $request->input('items', []);
        $cartItems = [];

        if (!empty($requestItems)) {
            // Convert frontend items format to cart format
            foreach ($requestItems as $item) {
                // Activity cart item (bilete.online activities flow). Shape:
                //   { type: 'activity', activity: {id,slug,organizer_id},
                //     variant: {id,name,price}, booking_date: 'YYYY-MM-DD',
                //     slot_start_time: 'HH:MM:SS', slot_end_time: 'HH:MM:SS',
                //     participants_count: N }
                // We normalise into a flat row tagged type=activity so the
                // event flow below can `continue;` past it cleanly.
                if (($item['type'] ?? null) === 'activity') {
                    $activityId       = $item['activity']['id']         ?? $item['activity_id']       ?? null;
                    $variantId        = $item['variant']['id']          ?? $item['variant_id']        ?? null;
                    $bookingDate      = $item['booking_date']           ?? null;
                    $slotStartTime    = $item['slot_start_time']        ?? null;
                    $slotEndTime      = $item['slot_end_time']          ?? null;
                    $participants     = (int) ($item['participants_count'] ?? $item['quantity'] ?? 1);
                    $variantPrice     = (float) ($item['variant']['price'] ?? $item['price'] ?? 0);
                    $variantName      = $item['variant']['name']        ?? $item['variant_name']      ?? 'Bilet';

                    if ($activityId && $variantId && $bookingDate && $slotStartTime && $participants > 0) {
                        $cartItems[] = [
                            'type'               => 'activity',
                            'activity_id'        => (int) $activityId,
                            'variant_id'         => (int) $variantId,
                            'booking_date'       => $bookingDate,
                            'slot_start_time'    => $slotStartTime,
                            'slot_end_time'      => $slotEndTime,
                            'participants_count' => $participants,
                            // legacy alias — some downstream code reads $item['quantity']
                            'quantity'           => $participants,
                            'price'              => $variantPrice,
                            'variant_name'       => $variantName,
                        ];
                    }
                    continue;
                }

                // Support both new AmbiletCart format and legacy format
                $eventId = $item['event']['id'] ?? $item['event_id'] ?? null;
                $ticketTypeId = $item['ticketType']['id'] ?? $item['ticket_type_id'] ?? null;
                $quantity = $item['quantity'] ?? 1;
                $price = $item['ticketType']['price'] ?? $item['price'] ?? 0;
                $ticketTypeName = $item['ticketType']['name'] ?? $item['ticket_type_name'] ?? 'Bilet';

                // Extract performance_id from cart item (from event data or direct field)
                $performanceId = $item['event']['performance_id'] ?? $item['performance_id'] ?? null;

                if ($eventId && $ticketTypeId) {
                    $cartItem = [
                        'event_id' => $eventId,
                        'ticket_type_id' => $ticketTypeId,
                        'performance_id' => $performanceId,
                        'quantity' => $quantity,
                        'price' => $price,
                        'ticket_type_name' => $ticketTypeName,
                    ];

                    // Preserve seat information if present
                    if (!empty($item['seat_uids'])) {
                        $cartItem['seat_uids'] = $item['seat_uids'];
                        $cartItem['event_seating_id'] = $item['event_seating_id'] ?? null;
                        $cartItem['seats'] = $item['seats'] ?? [];
                    }

                    // Preserve leisure venue metadata (visit_date, vehicle_info, tour_slot_time)
                    $cartItem['visit_date'] = $item['meta']['visit_date'] ?? $item['visit_date'] ?? null;
                    $cartItem['vehicle_info'] = $item['meta']['vehicle_info'] ?? $item['vehicle_info'] ?? null;
                    $cartItem['meta'] = $item['meta'] ?? null;

                    $cartItems[] = $cartItem;
                }
            }
        }

        // Fall back to database cart if no items in request
        if (empty($cartItems)) {
            $sessionId = $this->getSessionId($request);
            $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

            if (!$cart || $cart->isEmpty()) {
                return $this->error('Cart is empty', 400);
            }

            $cartItems = $cart->items ?? [];
        }

        if (empty($cartItems)) {
            return $this->error('Cart is empty', 400);
        }

        // Activity-aware split. Activity items go through a dedicated flow
        // (bookings + slot capacity instead of event ticket types). Mixed
        // carts are rejected for v1 — the two flows touch different stock
        // models and combining them in one transaction is footgun-prone.
        $activityCartItems = [];
        $eventCartItems    = [];
        foreach ($cartItems as $ci) {
            if (($ci['type'] ?? null) === 'activity') {
                $activityCartItems[] = $ci;
            } else {
                $eventCartItems[] = $ci;
            }
        }

        if (!empty($activityCartItems) && !empty($eventCartItems)) {
            return $this->error(
                'Coșul conține atât bilete la evenimente cât și rezervări pentru activități. Te rugăm să le finalizezi separat.',
                400
            );
        }

        if (!empty($activityCartItems)) {
            return $this->processActivityCheckout(
                $request,
                $client,
                $activityCartItems,
                $validated,
                $isTestOrder
            );
        }

        // Validate cart items are still available
        $validationErrors = $this->validateCartItemsForCheckout($cartItems, $isTestOrder);
        if (!empty($validationErrors)) {
            // info, not warning — this is a normal business event (sold-out
            // race / expired hold), not an application error. Customer sees
            // a clean message and the app handles it correctly.
            Log::channel('marketplace')->info('Checkout validation failed', [
                'client_id' => $client->id,
                'cart_items' => $cartItems,
                'errors' => $validationErrors,
            ]);
            return $this->error('Some items are no longer available', 400, [
                'errors' => $validationErrors,
            ]);
        }

        // Calculate per-ticket insurance amount
        if ($hasInsurance && $insuranceAmount > 0) {
            $totalTicketCount = collect($cartItems)->sum(fn($item) => (int) ($item['quantity'] ?? 1));
            $insurancePerTicket = $totalTicketCount > 0 ? round($insuranceAmount / $totalTicketCount, 2) : 0;
        }

        try {
            DB::beginTransaction();

            // Find or create customer. firstOrCreate is atomic — closes the
            // race window the where->first + create pattern had under
            // concurrent checkouts on the same email (was firing 23505
            // unique violations on marketplace_customers tens of times a
            // week as "Checkout failed").
            $plainPassword = $validated['customer']['password'] ?? null;
            $autoCreatedPassword = null;

            $customer = MarketplaceCustomer::firstOrCreate(
                [
                    'marketplace_client_id' => $client->id,
                    'email' => $validated['customer']['email'],
                ],
                [
                    'first_name' => $validated['customer']['first_name'],
                    'last_name' => $validated['customer']['last_name'],
                    'phone' => $validated['customer']['phone'] ?? null,
                    'password' => $plainPassword ? Hash::make($plainPassword) : null,
                    'status' => 'active',
                ]
            );

            if ($customer->wasRecentlyCreated) {
                if ($plainPassword) {
                    $autoCreatedPassword = $plainPassword;
                }
            } else {
                // Existing customer — refresh profile fields from the request.
                $customer->update([
                    'first_name' => $validated['customer']['first_name'],
                    'last_name' => $validated['customer']['last_name'],
                    'phone' => $validated['customer']['phone'] ?? $customer->phone,
                ]);
                // If customer exists but has no password and one was provided, set it
                if (!$customer->password && !empty($validated['customer']['password'])) {
                    $plainPassword = $validated['customer']['password'];
                    $customer->update(['password' => Hash::make($plainPassword)]);
                    $autoCreatedPassword = $plainPassword;
                }
            }

            // Process all cart items into a single order (supports multi-event carts)
            $subtotal = 0;
            $totalCommission = 0;
            $totalOnTopCommission = 0;
            $processedItems = [];
            $seatedItemsMeta = [];
            $eventIds = [];
            $primaryEvent = null;
            $primaryMarketplaceEvent = null;
            $primaryOrganizerId = null;

            foreach ($cartItems as $item) {
                $eventId = $item['event_id'] ?? null;
                $quantity = (int) $item['quantity'];
                $ticketTypeId = $item['ticket_type_id'];

                // Resolve event
                $marketplaceEvent = MarketplaceEvent::find($eventId);
                $event = Event::with(['tenant', 'marketplaceOrganizer'])->find($eventId);

                if (!$event && !$marketplaceEvent) {
                    Log::channel('marketplace')->warning('Checkout: Event not found', ['event_id' => $eventId]);
                    continue;
                }

                // Track primary event (first one) for order-level fields
                if (!$primaryEvent && $event) {
                    $primaryEvent = $event;
                    $primaryMarketplaceEvent = $marketplaceEvent;
                    $primaryOrganizerId = $event->marketplace_organizer_id ?? $marketplaceEvent?->marketplace_organizer_id;
                }
                if ($eventId) {
                    $eventIds[$eventId] = true;
                }

                // Resolve ticket type
                $mktTicketType = null;
                $ticketType = null;

                if ($marketplaceEvent) {
                    $mktTicketType = MarketplaceTicketType::where('id', $ticketTypeId)
                        ->where('marketplace_event_id', $marketplaceEvent->id)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$mktTicketType) {
                    $ticketType = TicketType::where('id', $ticketTypeId)
                        ->when($event, fn ($q) => $q->where('event_id', $event->id))
                        ->lockForUpdate()
                        ->first();
                }

                if (!$ticketType && !$mktTicketType) {
                    throw new \Exception("Ticket type not found: {$ticketTypeId}");
                }

                // Check availability and update stock
                // Skip stock validation and decrement for test orders (allows repeated testing)
                if (!$isTestOrder) {
                    if ($mktTicketType) {
                        $available = $mktTicketType->quantity === null
                            ? PHP_INT_MAX
                            : max(0, $mktTicketType->quantity - ($mktTicketType->quantity_sold ?? 0) - ($mktTicketType->quantity_reserved ?? 0));

                        if ($available < $quantity) {
                            throw new \Exception("Not enough tickets for {$mktTicketType->name}");
                        }

                        $mktTicketType->increment('quantity_sold', $quantity);

                        if ($mktTicketType->quantity !== null && ($mktTicketType->quantity_sold >= $mktTicketType->quantity)) {
                            $mktTicketType->update(['status' => 'sold_out']);
                        }
                    } elseif ($ticketType) {
                        // Own stock availability
                        $ownAvailable = ($ticketType->quota_total === null || $ticketType->quota_total < 0)
                            ? PHP_INT_MAX
                            : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

                        // Shared pool check
                        $available = $ownAvailable;
                        $event = $ticketType->event;
                        if ($event && $event->general_quota !== null && !$ticketType->is_independent_stock) {
                            $soldNonIndep = $event->ticketTypes()
                                ->where('is_independent_stock', false)
                                ->sum('quota_sold');
                            $poolRemaining = max(0, $event->general_quota - (int) $soldNonIndep);
                            $available = min($ownAvailable, $poolRemaining);
                        }

                        if ($available < $quantity) {
                            throw new \Exception("Not enough tickets for {$ticketType->name}");
                        }

                        if ($ticketType->quota_total !== null && $ticketType->quota_total >= 0) {
                            $ticketType->increment('quota_sold', $quantity);

                            // Check low stock alert
                            $this->checkLowStockAlert($ticketType);
                        }
                    }

                    // Date capacity check for leisure venue events
                    $visitDate = $item['visit_date'] ?? null;
                    if ($visitDate && $mktTicketType && $mktTicketType->daily_capacity) {
                        $dateCap = \App\Models\MarketplaceEventDateCapacity::where('marketplace_event_id', $eventId)
                            ->where('marketplace_ticket_type_id', $ticketTypeId)
                            ->where('visit_date', $visitDate)
                            ->lockForUpdate()
                            ->first();

                        if (!$dateCap) {
                            $dateCap = \App\Models\MarketplaceEventDateCapacity::create([
                                'marketplace_event_id' => $eventId,
                                'marketplace_ticket_type_id' => $ticketTypeId,
                                'visit_date' => $visitDate,
                                'capacity' => $mktTicketType->daily_capacity,
                                'sold' => 0,
                                'reserved' => 0,
                            ]);
                        }

                        if ($dateCap->is_closed) {
                            throw new \Exception("Tickets for {$mktTicketType->name} are not available on {$visitDate}");
                        }

                        if ($dateCap->available < $quantity) {
                            throw new \Exception("Not enough tickets for {$mktTicketType->name} on {$visitDate}");
                        }

                        $dateCap->increment('sold', $quantity);
                    }
                }

                // Determine unit price
                if ($mktTicketType) {
                    // For leisure venues, use effective price (includes pricing rules + date overrides)
                    $visitDate = $item['visit_date'] ?? null;
                    if ($visitDate && $marketplaceEvent?->isLeisureVenue()) {
                        $dateOverride = null;
                        $dateCap = \App\Models\MarketplaceEventDateCapacity::where('marketplace_event_id', $eventId)
                            ->where('marketplace_ticket_type_id', $ticketTypeId)
                            ->where('visit_date', $visitDate)
                            ->first();
                        if ($dateCap && $dateCap->price_override !== null) {
                            $dateOverride = (float) $dateCap->price_override;
                        }
                        $unitPrice = $marketplaceEvent->getEffectivePrice($mktTicketType, $visitDate, $dateOverride);
                    } else {
                        $unitPrice = (float) $mktTicketType->price;
                    }
                } elseif ($ticketType) {
                    $unitPrice = ($ticketType->sale_price_cents ?? $ticketType->price_cents) / 100;
                } else {
                    $unitPrice = (float) ($item['price'] ?? 0);
                }

                $itemTotal = $unitPrice * $quantity;
                $subtotal += $itemTotal;

                // Calculate per-item commission
                // Fallback rate from event > organizer > tenant > client
                $defaultCommissionRate = $event?->commission_rate
                    ?? $event?->marketplaceOrganizer?->commission_rate
                    ?? $event?->tenant?->commission_rate
                    ?? $client->commission_rate
                    ?? 5;
                $defaultCommissionMode = $event?->commission_mode
                    ?? $event?->marketplaceOrganizer?->default_commission_mode
                    ?? $client->commission_mode
                    ?? 'included';

                // Use ticket-type commission override if available
                $itemCommission = 0;
                $itemCommissionMode = $defaultCommissionMode;
                $itemCommissionRate = $defaultCommissionRate;

                if ($ticketType && $ticketType->commission_type) {
                    // Ticket type has its own commission settings
                    $itemCommission = $ticketType->calculateCommission($unitPrice, $defaultCommissionRate, $defaultCommissionMode) * $quantity;
                    $effective = $ticketType->getEffectiveCommission($defaultCommissionRate, $defaultCommissionMode);
                    $itemCommissionRate = $effective['rate'];
                    $itemCommissionMode = $effective['mode'];
                } else {
                    // Use default rate
                    $itemCommission = round($itemTotal * ($defaultCommissionRate / 100), 2);
                }
                $totalCommission += $itemCommission;

                // Track on-top commission per item
                if (in_array($itemCommissionMode, ['on_top', 'added_on_top'])) {
                    $totalOnTopCommission += $itemCommission;
                }

                // Seat-consistency safety net. For seated events the cart
                // item MUST carry exactly as many seat_uids as the quantity
                // — otherwise the ticket-creation loop below silently emits
                // tickets with no seat metadata (no row/seat, not blocked
                // on the map). This protected one observed case where a
                // customer ended up with 3 paid tickets and zero seats
                // because a front-end edge case stripped seat_uids while
                // leaving quantity intact. Reject the whole checkout so
                // they go back and re-pick instead of paying for blanks.
                $itemSeatUids = $item['seat_uids'] ?? [];
                $hasSeatedTicketType = ($ticketType && method_exists($ticketType, 'getAttribute') && $ticketType->getAttribute('has_seating'))
                    || ($mktTicketType && method_exists($mktTicketType, 'getAttribute') && $mktTicketType->getAttribute('has_seating'));
                if (!empty($item['event_seating_id']) || $hasSeatedTicketType || !empty($itemSeatUids)) {
                    if (count($itemSeatUids) !== (int) $quantity) {
                        Log::channel('marketplace')->warning('Checkout: seat/quantity mismatch on seated item', [
                            'client_id' => $client->id,
                            'event_id' => $event?->id ?? $marketplaceEvent?->id,
                            'ticket_type_id' => $ticketType?->id ?? $mktTicketType?->id,
                            'quantity' => $quantity,
                            'seat_uids_count' => count($itemSeatUids),
                            'event_seating_id' => $item['event_seating_id'] ?? null,
                        ]);
                        return $this->error(
                            'Numărul de bilete nu corespunde cu locurile alese pentru ' . ($ticketType?->name ?? $mktTicketType?->name ?? 'unul dintre tipurile de bilete') . '. Te rugăm să te întorci pe pagina evenimentului și să alegi din nou locurile.',
                            422,
                            [
                                'code' => 'seat_quantity_mismatch',
                                'ticket_type_id' => $ticketType?->id ?? $mktTicketType?->id,
                                'expected_seats' => (int) $quantity,
                                'received_seats' => count($itemSeatUids),
                            ]
                        );
                    }
                }

                $processedItems[] = [
                    'event' => $event,
                    'marketplace_event' => $marketplaceEvent,
                    'ticket_type' => $ticketType,
                    'marketplace_ticket_type' => $mktTicketType,
                    'performance_id' => $item['performance_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $itemTotal,
                    'commission_amount' => $itemCommission,
                    'commission_rate' => $itemCommissionRate,
                    'commission_mode' => $itemCommissionMode,
                    'seat_uids' => $item['seat_uids'] ?? [],
                    'seats' => $item['seats'] ?? [],
                    'event_seating_id' => $item['event_seating_id'] ?? null,
                    'visit_date' => $item['visit_date'] ?? null,
                    'vehicle_info' => $item['vehicle_info'] ?? null,
                ];

                // Collect seated items for order meta
                if (!empty($item['seat_uids']) && !empty($item['event_seating_id'])) {
                    $seatedItemsMeta[] = [
                        'event_seating_id' => (int) $item['event_seating_id'],
                        'seat_uids' => $item['seat_uids'],
                    ];
                }
            }

            // Two-phase guard against duplicate pending orders from the same
            // customer for the same event:
            //
            // Phase 1 (dedupe — same seats):
            //   Customer re-clicks Cumpără on the same seats. Return the
            //   existing pending order, do NOT create a new one.
            //
            // Phase 2 (auto-cancel — different seats, same event):
            //   Customer switches seats and tries again. Cancel the
            //   previous pending order(s) for this event so the customer
            //   only ever has 1 active pending order per (customer, event).
            //   Releases the old seats automatically.
            //
            // Test orders (source=test_order) skip both guards — QA may
            // need rapid duplicate inserts.
            if (!empty($seatedItemsMeta) && !$isTestOrder) {
                $allRequestedSeatUids = collect($seatedItemsMeta)
                    ->flatMap(fn ($i) => $i['seat_uids'] ?? [])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                // Resolve event ids touched by the new cart (each processed
                // item carries its own event reference; the order's
                // event_id is null for multi-event flows).
                $newEventIds = collect($processedItems)
                    ->map(fn ($pi) => $pi['event']?->id ?? $pi['marketplace_event']?->id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($allRequestedSeatUids) && !empty($newEventIds)) {
                    $existingPending = Order::where('marketplace_customer_id', $customer->id)
                        ->where('marketplace_client_id', $client->id)
                        ->where('status', 'pending')
                        ->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->where('created_at', '>=', now()->subHour())
                        ->where(function ($q) use ($newEventIds) {
                            $q->whereIn('event_id', $newEventIds)
                              ->orWhereIn('marketplace_event_id', $newEventIds);
                        })
                        ->orderByDesc('created_at')
                        ->get(['id', 'order_number', 'total', 'currency', 'meta', 'event_id', 'marketplace_event_id', 'created_at', 'expires_at']);

                    $ordersToCancel = [];
                    foreach ($existingPending as $candidate) {
                        $candidateSeatedItems = is_array($candidate->meta)
                            ? ($candidate->meta['seated_items'] ?? [])
                            : [];
                        $candidateSeatUids = collect($candidateSeatedItems)
                            ->flatMap(fn ($i) => $i['seat_uids'] ?? [])
                            ->filter()
                            ->all();
                        $overlap = array_intersect($allRequestedSeatUids, $candidateSeatUids);

                        if (!empty($overlap)) {
                            // Phase 1: same seats → return existing order.
                            Log::channel('marketplace')->warning('Checkout: dedupe — same customer + overlapping seats', [
                                'customer_id' => $customer->id,
                                'existing_order_id' => $candidate->id,
                                'overlap_seat_uids' => array_values($overlap),
                                'requested_seat_uids' => $allRequestedSeatUids,
                            ]);
                            return $this->success([
                                'order_id' => $candidate->id,
                                'order_number' => $candidate->order_number,
                                'total' => (float) $candidate->total,
                                'currency' => $candidate->currency,
                                'duplicate' => true,
                                'message' => 'Ai deja o comandă în așteptare pentru aceste locuri. Te trimitem la plata existentă.',
                            ], 'Existing pending order returned');
                        }

                        // Phase 2: different seats, same event → queue for
                        // cancellation. We collect first then cancel after
                        // the loop so a thrown error halfway doesn't leave
                        // half-cancelled state visible to the customer.
                        $ordersToCancel[] = $candidate;
                    }

                    // Phase 2 execute: cancel + release seats for each
                    // queued previous order. Wrapped in a transaction so
                    // either all cancellations succeed or none.
                    if (!empty($ordersToCancel)) {
                        DB::transaction(function () use ($ordersToCancel, $customer) {
                            foreach ($ordersToCancel as $oldOrder) {
                                $oldSeatedItems = is_array($oldOrder->meta)
                                    ? ($oldOrder->meta['seated_items'] ?? [])
                                    : [];
                                $releasedSeats = 0;
                                $ownTicketIds = \App\Models\Ticket::where('order_id', $oldOrder->id)->pluck('id')->all();

                                foreach ($oldSeatedItems as $item) {
                                    if (empty($item['event_seating_id']) || empty($item['seat_uids'])) continue;
                                    // Release ONLY held seats — never sold
                                    // ones (same defensive guard as the
                                    // cleanup cron fix).
                                    $releasedSeats += \App\Models\Seating\EventSeat::query()
                                        ->where('event_seating_id', $item['event_seating_id'])
                                        ->whereIn('seat_uid', $item['seat_uids'])
                                        ->where('status', 'held')
                                        ->where(function ($q) use ($ownTicketIds) {
                                            $q->whereNull('sold_to_ticket_id');
                                            if (!empty($ownTicketIds)) {
                                                $q->orWhereIn('sold_to_ticket_id', $ownTicketIds);
                                            }
                                        })
                                        ->update([
                                            'status' => 'available',
                                            'version' => DB::raw('version + 1'),
                                            'last_change_at' => now(),
                                        ]);

                                    // Drop only this order's own holds (keep
                                    // active holds of other in-checkout
                                    // sessions intact, even if they happened
                                    // to be on the same seats — those will
                                    // re-grab via the available state we just
                                    // restored).
                                    \App\Models\Seating\SeatHold::where('event_seating_id', $item['event_seating_id'])
                                        ->whereIn('seat_uid', $item['seat_uids'])
                                        ->where('expires_at', '<=', now()->addMinutes(60))
                                        ->delete();
                                }

                                // Cancel pending tickets of the old order.
                                \App\Models\Ticket::where('order_id', $oldOrder->id)
                                    ->where('status', 'pending')
                                    ->update(['status' => 'cancelled']);

                                // Mark order cancelled. Don't touch quota_sold
                                // — pending tickets never decremented it on
                                // checkout (only paid/valid tickets do).
                                $oldOrder->update([
                                    'status' => 'cancelled',
                                    'payment_status' => 'cancelled',
                                ]);

                                Log::channel('marketplace')->info('Checkout: auto-cancelled previous pending order for same customer/event with different seats', [
                                    'customer_id' => $customer->id,
                                    'cancelled_order_id' => $oldOrder->id,
                                    'released_seat_count' => $releasedSeats,
                                ]);
                            }
                        });
                    }
                }
            }

            // Apply discount from promo code
            $discount = 0;
            $promoCode = null;
            $promoCodeInput = $request->input('promo_code');
            // Indices into $processedItems that the promo is eligible for.
            // Set inside the matching branch below; used after the discount
            // block to allocate per-ticket discount_amount so views display
            // the correct discounted price only on tickets the coupon
            // actually applied to (instead of spreading proportionally
            // across all tickets in mixed-eligibility carts).
            $eligibleItemIndices = null;

            if ($promoCodeInput) {
                $promoCodeInput = strtoupper(trim($promoCodeInput));

                // Build cart data for validation
                $promoCartData = [
                    'event_id' => $primaryEvent?->id ?? $primaryMarketplaceEvent?->id,
                    'total' => $subtotal,
                    'ticket_count' => collect($processedItems)->sum('quantity'),
                    'items' => collect($processedItems)->map(fn ($pi) => [
                        'event_id' => $pi['event']?->id ?? $pi['marketplace_event']?->id,
                        // CouponCodes use TicketType IDs, organizer promos use MarketplaceTicketType IDs
                        'ticket_type_id' => $pi['ticket_type']?->id ?? $pi['marketplace_ticket_type']?->id,
                        'marketplace_ticket_type_id' => $pi['marketplace_ticket_type']?->id,
                        'quantity' => $pi['quantity'],
                        'total' => $pi['total'],
                    ])->all(),
                ];

                // 1. Try organizer promo codes (mkt_promo_codes)
                $orgPromo = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $client->id)
                    ->where('code', $promoCodeInput)
                    ->first();

                if ($orgPromo) {
                    $validation = $orgPromo->validateForCart($promoCartData, $customer->email);
                    if ($validation['valid']) {
                        $calculation = $orgPromo->calculateDiscount($promoCartData);
                        $discount = $calculation['discount_amount'];
                        $promoCode = [
                            'code' => $orgPromo->code,
                            'type' => $orgPromo->type,
                            'value' => (float) $orgPromo->value,
                            'source' => 'organizer',
                            'id' => $orgPromo->id,
                        ];

                        // Track which line items the promo is eligible for
                        // — mirror the same `applies_to` filter the model
                        // uses internally to compute the discount amount.
                        $appliesTo = $orgPromo->applies_to;
                        $eligibleItemIndices = [];
                        if ($appliesTo === 'specific_event') {
                            $targetEventId = (int) $orgPromo->marketplace_event_id;
                            foreach ($processedItems as $idx => $pi) {
                                $eId = (int) ($pi['event']?->id ?? $pi['marketplace_event']?->id ?? 0);
                                if ($eId === $targetEventId) {
                                    $eligibleItemIndices[] = $idx;
                                }
                            }
                        } elseif ($appliesTo === 'ticket_type') {
                            $allowedIds = $orgPromo->getApplicableTicketTypeIdsList();
                            foreach ($processedItems as $idx => $pi) {
                                $ttId = (int) ($pi['marketplace_ticket_type']?->id ?? $pi['ticket_type']?->id ?? 0);
                                if ($ttId > 0 && in_array($ttId, $allowedIds, true)) {
                                    $eligibleItemIndices[] = $idx;
                                }
                            }
                        } else {
                            // all_events (or unrecognized) → whole cart
                            $eligibleItemIndices = array_keys($processedItems);
                        }
                    }
                }

                // 2. Try coupon codes (coupon_codes) if no organizer promo matched
                if (!$promoCode) {
                    $coupon = CouponCode::where('marketplace_client_id', $client->id)
                        ->where('code', $promoCodeInput)
                        ->first();

                    if ($coupon && $coupon->isValid()) {
                        // Build the set of event IDs the coupon applies to, combining
                        // applicable_events (explicit list) with marketplace_organizer_id
                        // (all events of that organizer). Either restriction narrows the
                        // discount base; together they intersect.
                        $applicableEventIdsExplicit = !empty($coupon->applicable_events)
                            ? array_map('intval', $coupon->applicable_events)
                            : null;
                        $organizerEventIds = null;
                        if ($coupon->marketplace_organizer_id) {
                            $organizerEventIds = \App\Models\Event::where('marketplace_organizer_id', $coupon->marketplace_organizer_id)
                                ->pluck('id')
                                ->map(fn ($id) => (int) $id)
                                ->all();
                        }

                        $eventMatches = function ($evId) use ($applicableEventIdsExplicit, $organizerEventIds) {
                            $evId = (int) $evId;
                            if ($applicableEventIdsExplicit !== null && !in_array($evId, $applicableEventIdsExplicit, true)) {
                                return false;
                            }
                            if ($organizerEventIds !== null && !in_array($evId, $organizerEventIds, true)) {
                                return false;
                            }
                            return true;
                        };

                        $applicableTicketTypes = $coupon->applicable_ticket_types ?? [];
                        $hasTicketTypeFilter = !empty($applicableTicketTypes);
                        $hasEventFilter = $applicableEventIdsExplicit !== null || $organizerEventIds !== null;

                        // Compute the discount base over items that pass every configured filter.
                        $discountBase = 0.0;
                        if ($hasTicketTypeFilter || $hasEventFilter) {
                            foreach ($promoCartData['items'] as $item) {
                                $itemEventId = (int) ($item['event_id'] ?? 0);
                                if ($hasEventFilter && !$eventMatches($itemEventId)) {
                                    continue;
                                }
                                if ($hasTicketTypeFilter) {
                                    $ttId = (int) ($item['ticket_type_id'] ?? 0);
                                    if (!in_array($ttId, array_map('intval', $applicableTicketTypes), true)) {
                                        continue;
                                    }
                                }
                                $discountBase += (float) ($item['total'] ?? 0);
                            }
                        } else {
                            $discountBase = $subtotal;
                        }

                        if ($discountBase > 0) {
                            $discount = $coupon->calculateDiscount($discountBase);
                            $promoCode = [
                                'code' => $coupon->code,
                                'type' => $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed',
                                'value' => (float) $coupon->discount_value,
                                'source' => 'coupon',
                                'id' => $coupon->id,
                            ];

                            // Same predicate the loop above used to build
                            // $discountBase — collect the corresponding
                            // $processedItems indices so we can allocate
                            // the discount per ticket later.
                            $eligibleItemIndices = [];
                            foreach ($processedItems as $idx => $pi) {
                                $itemEventId = (int) ($pi['event']?->id ?? $pi['marketplace_event']?->id ?? 0);
                                if ($hasEventFilter && !$eventMatches($itemEventId)) {
                                    continue;
                                }
                                if ($hasTicketTypeFilter) {
                                    $ttId = (int) ($pi['ticket_type']?->id ?? 0);
                                    if (!in_array($ttId, array_map('intval', $applicableTicketTypes), true)) {
                                        continue;
                                    }
                                }
                                $eligibleItemIndices[] = $idx;
                            }
                            if (empty($eligibleItemIndices)) {
                                // No-filter case: $discountBase fell back
                                // to $subtotal, so every item is eligible.
                                $eligibleItemIndices = array_keys($processedItems);
                            }
                        }
                    }
                }
            } elseif (isset($cart) && $cart->promo_code) {
                // Fallback: read from database cart (legacy)
                $promoCode = $cart->promo_code;
                $discount = (float) ($cart->discount ?? 0);
                // No eligibility info from the legacy cart — assume all
                // items eligible so the discount spreads over the whole
                // cart (matches previous behavior).
                $eligibleItemIndices = array_keys($processedItems);
            }

            // Allocate the order-level discount across line items.
            // Eligible items share the discount proportionally to their
            // line total; non-eligible items keep their full price. This
            // map is persisted onto each ticket's meta so views and PDFs
            // can show the exact per-ticket discount even when the promo
            // only covered a subset of the cart.
            $discountPerItem = []; // idx in $processedItems => discount RON
            if ($discount > 0 && is_array($eligibleItemIndices) && count($eligibleItemIndices) > 0) {
                $eligibleBase = 0.0;
                foreach ($eligibleItemIndices as $idx) {
                    $eligibleBase += (float) ($processedItems[$idx]['total'] ?? 0);
                }
                if ($eligibleBase > 0) {
                    $allocated = 0.0;
                    $lastIdx = end($eligibleItemIndices);
                    foreach ($eligibleItemIndices as $idx) {
                        if ($idx === $lastIdx) {
                            // Give the remainder to the last eligible
                            // line so allocations sum exactly to $discount
                            // (avoids penny drift from rounding).
                            $discountPerItem[$idx] = round($discount - $allocated, 2);
                        } else {
                            $share = round(((float) $processedItems[$idx]['total'] / $eligibleBase) * $discount, 2);
                            $discountPerItem[$idx] = $share;
                            $allocated += $share;
                        }
                    }
                }
            }

            // Determine primary commission mode (for order-level meta)
            $commissionMode = $primaryEvent?->commission_mode
                ?? $primaryEvent?->marketplaceOrganizer?->default_commission_mode
                ?? $client->commission_mode
                ?? 'included';

            // Calculate weighted average commission rate
            $avgCommissionRate = $subtotal > 0 ? round(($totalCommission / $subtotal) * 100, 2) : 0;

            $netAmount = $subtotal - $discount;

            // Final order total — add only the on-top portion of commission
            $orderTotal = $netAmount;
            if ($hasInsurance && $insuranceAmount > 0) {
                $orderTotal += $insuranceAmount;
            }
            if ($totalOnTopCommission > 0) {
                $orderTotal += $totalOnTopCommission;
            }

            // Cultural card surcharge
            $culturalCardSurcharge = 0;
            if ($request->input('payment_method') === 'card_cultural' && $request->has('cultural_card_surcharge')) {
                $culturalCardSurcharge = round((float) $request->input('cultural_card_surcharge'), 2);
                $orderTotal += $culturalCardSurcharge;
            }

            // ============================================================
            // F3 — Payment processing fee snapshot
            //
            // Per F1 spec, fee = floor(subtotal * percent / 100) + fixed_cents
            // applied on the customer-visible subtotal (ticket + commission +
            // insurance + cultural surcharge — i.e. $orderTotal up to this
            // point, BEFORE the fee itself).
            //
            // Kill switch: marketplace.payment_fees IS NULL → calculator
            // returns all-zero, $orderTotal stays identical. Backward-compat
            // for Ambilet / Tics is preserved.
            // ============================================================
            $processingFee = [
                'fee_cents'        => 0,
                'pass_to_customer' => false,
                'provider'         => null,
                'percent_rate'     => null,
                'fixed_cents'      => null,
                'active'           => false,
            ];
            $isCardPayment = in_array($request->input('payment_method', 'card'), ['card', 'card_cultural'], true);
            // Only meaningful for paid card transactions — test orders, free
            // orders, and non-card payments skip the snapshot entirely.
            if (! $isTestOrder && $orderTotal > 0 && $isCardPayment) {
                $calc = app(\App\Services\Payments\ProcessingFeeCalculator::class);
                $subtotalCents = (int) round($orderTotal * 100);
                $organizer = $primaryOrganizerId
                    ? \App\Models\MarketplaceOrganizer::find($primaryOrganizerId)
                    : null;
                // Provider key — default 'stripe' for card payments. Marketplaces
                // that haven't configured 'stripe' in payment_fees.providers get
                // all-zero back from the calculator (no fee, no change to total).
                $providerKey = 'stripe';
                $processingFee = $calc->compute($client, $organizer, $providerKey, $subtotalCents);

                if ($processingFee['pass_to_customer'] && $processingFee['fee_cents'] > 0) {
                    $orderTotal += $processingFee['fee_cents'] / 100;
                }
            }

            $currency = isset($cart) ? $cart->currency : ($client->currency ?? 'RON');
            $isMultiEvent = count($eventIds) > 1;

            // Create single order
            // Auto-confirm: test orders AND free orders (total = 0)
            $isFreeOrder = !$isTestOrder && $orderTotal <= 0;
            $isAutoConfirmed = $isTestOrder || $isFreeOrder;

            // Newsletter attribution — JS sends `nl` from URL/localStorage
            // (see resources/marketplaces/ambilet/assets/js/newsletter-attribution.js).
            // Validate against the marketplace so a forged value can't
            // credit an unrelated organizer.
            $newsletterAttributionId = null;
            $nlRaw = $request->input('newsletter_attribution_id') ?? $request->input('nl');
            if (is_numeric($nlRaw) && (int) $nlRaw > 0) {
                $nlId = (int) $nlRaw;
                $nlExists = \DB::table('marketplace_newsletters')
                    ->where('id', $nlId)
                    ->where('marketplace_client_id', $client->id)
                    ->exists();
                if ($nlExists) $newsletterAttributionId = $nlId;
            }

            $order = Order::create([
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $isMultiEvent ? null : $primaryOrganizerId,
                'newsletter_attribution_id' => $newsletterAttributionId,
                'tenant_id' => $primaryEvent?->tenant_id,
                'marketplace_customer_id' => $customer->id,
                'event_id' => $isMultiEvent ? null : ($primaryEvent?->id),
                'marketplace_event_id' => $isMultiEvent ? null : ($primaryMarketplaceEvent?->id),
                'order_number' => ($isTestOrder ? 'TEST-' : 'MKT-') . strtoupper(Str::random(8)),
                'status' => $isAutoConfirmed ? 'completed' : 'pending',
                'payment_status' => $isTestOrder ? 'test' : ($isFreeOrder ? 'free' : 'pending'),
                'subtotal' => $isTestOrder ? 0 : $subtotal,
                'discount_amount' => $isTestOrder ? 0 : $discount,
                'commission_rate' => $isTestOrder ? 0 : $avgCommissionRate,
                'commission_amount' => $isTestOrder ? 0 : $totalCommission,
                'total' => $isTestOrder ? 0 : $orderTotal,
                // F3 — processing fee snapshot. Zero for marketplaces without
                // payment_fees configured (kill switch active). Stays bit-identical
                // to legacy behavior on Ambilet / Tics.
                'processing_fee_cents'        => $isTestOrder ? 0 : (int) ($processingFee['fee_cents'] ?? 0),
                'processing_fee_passed'       => (bool) ($processingFee['pass_to_customer'] ?? false),
                'processing_fee_provider'     => $processingFee['provider'] ?? null,
                'processing_fee_percent_rate' => $processingFee['percent_rate'] ?? null,
                'processing_fee_fixed_cents'  => $processingFee['fixed_cents'] ?? null,
                'currency' => $currency,
                'source' => $isTestOrder ? 'test_order' : ($isFreeOrder ? 'marketplace_free' : 'marketplace'),
                'customer_email' => $customer->email,
                'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                'customer_phone' => $customer->phone,
                'expires_at' => $isAutoConfirmed ? null : now()->addMinutes(15),
                'paid_at' => $isAutoConfirmed ? now() : null,
                'meta' => array_merge([
                    'cart_id' => isset($cart) ? $cart->id : null,
                    'promo_code' => $promoCode,
                    'beneficiaries' => $validated['beneficiaries'] ?? [],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'ticket_insurance' => $hasInsurance && $insuranceAmount > 0,
                    'insurance_amount' => $hasInsurance ? $insuranceAmount : 0,
                    'cultural_card_surcharge' => $culturalCardSurcharge > 0 ? $culturalCardSurcharge : null,
                    'payment_method' => $isTestOrder ? 'test' : $request->input('payment_method', 'card'),
                    'commission_mode' => $commissionMode,
                    'commission_details' => collect($processedItems)->map(fn ($pi) => [
                        'ticket_type' => ($pi['marketplace_ticket_type']?->name ?? $pi['ticket_type']?->name ?? 'Bilet'),
                        'quantity' => $pi['quantity'],
                        'unit_price' => $pi['unit_price'],
                        'total' => $pi['total'],
                        'commission_rate' => $pi['commission_rate'],
                        'commission_amount' => $pi['commission_amount'],
                        'commission_mode' => $pi['commission_mode'],
                    ])->all(),
                    'multi_event' => $isMultiEvent,
                    'event_ids' => array_keys($eventIds),
                    'seated_items' => $seatedItemsMeta,
                ], $isTestOrder ? ['is_test_order' => true] : []),
            ]);

            // Create order items and pending tickets
            $ticketIndex = 0;
            foreach ($processedItems as $idx => $item) {
                $itemDiscountTotal = (float) ($discountPerItem[$idx] ?? 0);
                $perTicketDiscount = ($item['quantity'] > 0 && $itemDiscountTotal > 0)
                    ? round($itemDiscountTotal / $item['quantity'], 2)
                    : 0;
                $mtt = $item['marketplace_ticket_type'];
                $tt = $item['ticket_type'];
                $event = $item['event'];
                $marketplaceEvent = $item['marketplace_event'];

                if ($mtt) {
                    $itemName = is_array($mtt->name)
                        ? ($mtt->name['ro'] ?? $mtt->name['en'] ?? array_values($mtt->name)[0] ?? 'Bilet')
                        : ($mtt->name ?? 'Bilet');
                } elseif ($tt) {
                    $itemName = is_array($tt->name)
                        ? ($tt->name['ro'] ?? $tt->name['en'] ?? array_values($tt->name)[0] ?? 'Bilet')
                        : ($tt->name ?? 'Bilet');
                } else {
                    $itemName = 'Bilet';
                }

                $orderItem = $order->items()->create([
                    'ticket_type_id' => $tt->id ?? $mtt->id ?? null,
                    'performance_id' => $item['performance_id'] ?? null,
                    'name' => $itemName,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                    'meta' => [
                        'event_id' => $event?->id ?? $marketplaceEvent?->id,
                    ],
                ]);

                for ($i = 0; $i < $item['quantity']; $i++) {
                    $beneficiary = $validated['beneficiaries'][$ticketIndex] ?? null;

                    $seatUid = $item['seat_uids'][$i] ?? null;
                    $seatInfo = null;
                    if ($seatUid && !empty($item['seats'])) {
                        foreach ($item['seats'] as $seat) {
                            if (($seat['seat_uid'] ?? null) === $seatUid) {
                                $seatInfo = $seat;
                                break;
                            }
                        }
                    }

                    $seatLabel = null;
                    $seatMeta = null;

                    if ($seatUid) {
                        $labelParts = [];
                        if ($seatInfo) {
                            if (!empty($seatInfo['section_name'])) {
                                $labelParts[] = $seatInfo['section_name'];
                            }
                            if (!empty($seatInfo['row_label'])) {
                                $labelParts[] = 'Row ' . $seatInfo['row_label'];
                            }
                            if (!empty($seatInfo['seat_label'])) {
                                $labelParts[] = 'Seat ' . $seatInfo['seat_label'];
                            }
                        }
                        $seatLabel = !empty($labelParts) ? implode(', ', $labelParts) : $seatUid;

                        $seatMeta = [
                            'seat_uid' => $seatUid,
                            'event_seating_id' => $item['event_seating_id'],
                            'section_name' => $seatInfo['section_name'] ?? null,
                            'row_label' => $seatInfo['row_label'] ?? null,
                            'seat_number' => $seatInfo['seat_label'] ?? null,
                        ];
                    }

                    $ticketMeta = $seatMeta ?? [];
                    if ($hasInsurance && $insurancePerTicket > 0) {
                        $ticketMeta['has_insurance'] = true;
                        $ticketMeta['insurance_amount'] = $insurancePerTicket;
                    }
                    if ($discount > 0) {
                        // Always write per-ticket discount when the order
                        // has any discount — including 0 for ineligible
                        // tickets in mixed-eligibility carts. The 0
                        // explicitly tells getEffectivePrice "don't fall
                        // back to the proportional approximation for this
                        // ticket", so non-covered tickets display their
                        // full price instead of an incorrect fraction.
                        $ticketMeta['discount_amount'] = $perTicketDiscount;
                    }

                    // Leisure venue metadata
                    if (!empty($item['visit_date'])) {
                        $ticketMeta['visit_date'] = $item['visit_date'];
                    }
                    if (!empty($item['vehicle_info'])) {
                        $ticketMeta['vehicle_info'] = $item['vehicle_info'];
                    }
                    // Tour slot time (guided tours)
                    $tourSlot = $item['meta']['tour_slot_time'] ?? $item['tour_slot_time'] ?? null;
                    if ($tourSlot) {
                        $ticketMeta['tour_slot_time'] = $tourSlot;
                    }

                    Ticket::create([
                        'marketplace_client_id' => $client->id,
                        'tenant_id' => $event?->tenant_id,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'event_id' => $event?->id,
                        'marketplace_event_id' => $marketplaceEvent?->id,
                        'ticket_type_id' => $tt->id ?? null,
                        'marketplace_ticket_type_id' => $mtt->id ?? null,
                        'performance_id' => $item['performance_id'] ?? null,
                        'marketplace_customer_id' => $customer->id,
                        'code' => strtoupper(Str::random(8)),
                        'barcode' => Str::uuid()->toString(),
                        'status' => $isAutoConfirmed ? 'valid' : 'pending',
                        'price' => $isTestOrder ? 0 : $item['unit_price'],
                        'seat_label' => $seatLabel,
                        // Denormalised seat_uid for the partial unique index
                        // (migration 2026_05_22_110000). Kept in sync with
                        // meta.seat_uid so the DB can enforce the
                        // no-double-booking invariant. Null for general
                        // admission tickets.
                        'seat_uid' => $seatUid ?? null,
                        'attendee_name' => $beneficiary['name'] ?? null,
                        'attendee_email' => $beneficiary['email'] ?? null,
                        'meta' => !empty($ticketMeta) ? $ticketMeta : null,
                    ]);

                    $ticketIndex++;
                }
            }

            // Increment promo code usage
            if ($promoCode && isset($promoCode['code'])) {
                $promoSource = $promoCode['source'] ?? null;
                if ($promoSource === 'organizer' && !empty($promoCode['id'])) {
                    $orgPromoModel = MarketplaceOrganizerPromoCode::find($promoCode['id']);
                    if ($orgPromoModel) {
                        $orgPromoModel->recordUsage($order, $discount, $customer, $request->ip());
                    }
                } elseif ($promoSource === 'coupon' && !empty($promoCode['id'])) {
                    CouponCode::where('id', $promoCode['id'])->increment('current_uses');
                } else {
                    // Legacy fallback
                    MarketplacePromoCode::where('code', $promoCode['code'])->increment('times_used');
                }
            }

            // Extend seat holds to match order expiration
            $orderExpiresAt = now()->addMinutes(15);
            foreach ($seatedItemsMeta as $seatedItem) {
                \App\Models\Seating\SeatHold::where('event_seating_id', $seatedItem['event_seating_id'])
                    ->whereIn('seat_uid', $seatedItem['seat_uids'])
                    ->update(['expires_at' => $orderExpiresAt]);

                Log::channel('marketplace')->info('Checkout: Extended seat holds for payment', [
                    'client_id' => $client->id,
                    'event_seating_id' => $seatedItem['event_seating_id'],
                    'seat_count' => count($seatedItem['seat_uids']),
                    'hold_extended_to' => $orderExpiresAt->toIso8601String(),
                ]);
            }

            // Build event name for response
            $eventName = 'Comandă';
            if ($primaryEvent) {
                $title = $primaryEvent->getTranslation('title', 'ro');
                if (is_string($title) && $title !== '') {
                    $eventName = $title;
                } elseif (is_array($title)) {
                    $eventName = $title['ro'] ?? $title['en'] ?? reset($title) ?: 'Event';
                }
                if ($isMultiEvent) {
                    $eventName .= ' + ' . (count($eventIds) - 1) . ' alt' . (count($eventIds) > 2 ? 'e evenimente' : ' eveniment');
                }
            }

            // Clear the cart if it exists in database
            if (isset($cart)) {
                $cart->clearItems();
                $cart->save();
            }

            DB::commit();

            // Send account created email with set-password link
            // Uses marketplace mail transport (same as order confirmation) for reliable delivery
            if ($autoCreatedPassword) {
                try {
                    // Generate password reset token so user can set their own password
                    $setPasswordToken = \Illuminate\Support\Str::random(64);
                    DB::table('marketplace_password_resets')
                        ->where('email', $customer->email)
                        ->where('type', 'customer')
                        ->where('marketplace_client_id', $client->id)
                        ->delete();
                    DB::table('marketplace_password_resets')->insert([
                        'email' => $customer->email,
                        'type' => 'customer',
                        'marketplace_client_id' => $client->id,
                        'token' => Hash::make($setPasswordToken),
                        'created_at' => now(),
                    ]);

                    $this->sendAccountCreatedEmail($client, $customer, $setPasswordToken);
                } catch (\Exception $e) {
                    Log::channel('marketplace')->warning('Failed to send account created email', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Build response (keep orders array format for backwards compatibility)
            $orders = [[
                'id' => $order->id,
                'order_number' => $order->order_number,
                'event' => [
                    'id' => $primaryEvent?->id ?? $primaryMarketplaceEvent?->id,
                    'name' => $eventName,
                ],
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount_amount,
                'insurance' => (float) ($hasInsurance ? $insuranceAmount : 0),
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'expires_at' => $order->expires_at?->toIso8601String(),
            ]];

            Log::channel('marketplace')->info('Checkout completed', [
                'client_id' => $client->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'multi_event' => $isMultiEvent,
                'event_count' => count($eventIds),
                'total' => (float) $order->total,
            ]);

            return $this->success([
                'orders' => $orders,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'has_account' => $customer->password !== null,
                ],
                'payment_required' => (float) $order->total > 0,
            ], 'Checkout successful', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('marketplace')->error('Checkout failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Checkout failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Activity checkout flow.
     *
     * Mirrors the event checkout but reserves capacity on `activity_bookings`
     * + creates one Order with `event_id=null, source=marketplace_activity`.
     * Each cart item becomes:
     *   - one OrderItem (line on the order)
     *   - one ActivityBooking with status=pending_payment, held_until=+5min
     *   - N Ticket rows (N = participants_count), each with
     *     `activity_booking_id` set and `event_id/ticket_type_id` null
     *
     * Once the Order transitions to paid/confirmed (Netopia webhook,
     * PaymentController, or auto-confirmation for test/free orders), the
     * ActivityBookingOrderObserver flips bookings → paid (released hold
     * = capacity permanent) and tickets → valid.
     *
     * If the user abandons checkout, the ReleaseExpiredActivityHoldsCommand
     * cancels the booking ~ every minute and the slot capacity is returned.
     *
     * Insurance / promo codes are intentionally NOT applied in v1 — the
     * bilete.online activities pages don't surface them yet. The flow is
     * structurally compatible if those features are added later.
     */
    protected function processActivityCheckout(
        Request $request,
        $client,
        array $cartItems,
        array $validated,
        bool $isTestOrder
    ): JsonResponse {
        // Validate slot availability + variant/activity state first; cheaper
        // to fail before opening a DB transaction.
        $validationErrors = $this->validateActivityCartItems($cartItems);
        if (!empty($validationErrors)) {
            Log::channel('marketplace')->info('Activity checkout validation failed', [
                'client_id' => $client->id,
                'cart_items' => $cartItems,
                'errors' => $validationErrors,
            ]);
            return $this->error('Some activity slots are no longer available', 400, [
                'errors' => $validationErrors,
            ]);
        }

        try {
            DB::beginTransaction();

            // Find or create customer (same logic as event checkout — see the
            // comment on the event branch for why this is firstOrCreate
            // instead of where->first + create).
            $plainPassword = $validated['customer']['password'] ?? null;
            $autoCreatedPassword = null;

            $customer = MarketplaceCustomer::firstOrCreate(
                [
                    'marketplace_client_id' => $client->id,
                    'email' => $validated['customer']['email'],
                ],
                [
                    'first_name' => $validated['customer']['first_name'],
                    'last_name'  => $validated['customer']['last_name'],
                    'phone'      => $validated['customer']['phone'] ?? null,
                    'password'   => $plainPassword ? Hash::make($plainPassword) : null,
                    'status'     => 'active',
                ]
            );

            if ($customer->wasRecentlyCreated) {
                if ($plainPassword) {
                    $autoCreatedPassword = $plainPassword;
                }
            } else {
                $customer->update([
                    'first_name' => $validated['customer']['first_name'],
                    'last_name'  => $validated['customer']['last_name'],
                    'phone'      => $validated['customer']['phone'] ?? $customer->phone,
                ]);
                if (!$customer->password && !empty($validated['customer']['password'])) {
                    $plainPassword = $validated['customer']['password'];
                    $customer->update(['password' => Hash::make($plainPassword)]);
                    $autoCreatedPassword = $plainPassword;
                }
            }

            // Process each cart item: lock the slot's current bookings,
            // re-check remaining capacity inside the transaction, accumulate
            // subtotal/commission, and stage the booking shape for insertion.
            $subtotal             = 0;
            $totalCommission      = 0;
            // Subset of $totalCommission that came from "added_on_top"
            // lines. This is what gets added to the customer-facing total
            // (the rest is already baked into ticket prices for "included"
            // lines, so no double-charging).
            $commissionAddedOnTop = 0;
            $stagedBookings       = [];
            $primaryActivity      = null;
            $primaryOrganizerId   = null;
            $organizerIds         = [];

            foreach ($cartItems as $item) {
                // organizer.marketplaceClient is eager-loaded because
                // getEffectiveCommissionMode falls back to the marketplace
                // client's mode when the organizer's own mode is null.
                // Without this eager-load, that fallback triggers an N+1
                // and (worse) breaks under DB::transaction lazy-loading
                // restrictions on some setups.
                $activity = Activity::with([
                    'variants',
                    'schedules',
                    'scheduleExceptions',
                    'organizer:id,marketplace_client_id,commission_rate,default_commission_mode',
                    'organizer.marketplaceClient:id,commission_rate,commission_mode',
                ])->find($item['activity_id']);
                if (!$activity) {
                    throw new \Exception("Activity not found: {$item['activity_id']}");
                }

                $variant = $activity->variants
                    ->firstWhere('id', $item['variant_id']);
                if (!$variant || !$variant->is_active) {
                    throw new \Exception("Activity variant not available: {$item['variant_id']}");
                }

                $participants  = max(1, (int) $item['participants_count']);
                $capacityShare = max(1, (int) ($variant->capacity_share ?? 1));
                $slotConsumes  = $participants * $capacityShare;

                // Lock existing bookings for this slot so two concurrent
                // checkouts can't both pass the capacity check. The lock
                // is held until the transaction commits or rolls back.
                //
                // PostgreSQL note: cannot use `lockForUpdate()->sum(...)`
                // ("FOR UPDATE is not allowed with aggregate functions").
                // We select the raw column values WITH the row lock, then
                // sum in PHP — same lock semantics on the existing rows,
                // works on both MySQL and Postgres.
                $consumed = (int) DB::table('activity_bookings')
                    ->where('activity_id', $activity->id)
                    ->whereDate('booking_date', $item['booking_date'])
                    ->where('slot_start_time', $item['slot_start_time'])
                    ->whereIn('status', ActivityBooking::CAPACITY_CONSUMING_STATUSES)
                    ->where(function ($q) {
                        $q->where('status', '<>', ActivityBooking::STATUS_PENDING_PAYMENT)
                            ->orWhereNull('held_until')
                            ->orWhere('held_until', '>=', now());
                    })
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->pluck('participants_count')
                    ->sum();

                $capTotal  = max(1, (int) ($activity->capacity_per_slot ?? 1));
                $remaining = max(0, $capTotal - (int) $consumed);
                if ($slotConsumes > $remaining) {
                    throw new \Exception("Slotul nu mai are suficientă disponibilitate pentru această rezervare.");
                }

                // Line totals.
                $unitPrice  = $variant->price; // float, RON
                $lineTotal  = round($unitPrice * $participants, 2);
                $subtotal  += $lineTotal;

                // Commission resolution cascade:
                //   variant override → organizer effective → 0%.
                // Mode is always organizer-level (variants don't get to flip
                // included vs added_on_top). v1 was reading variant->commission_rate
                // with a 0 fallback only, which silently dropped the 2%
                // platform commission for every bilete.online order:
                // organizer had 2% added_on_top, variant had no override,
                // → commissionRate=0 → nothing collected, nothing added
                // to the customer's total. Customer pays subtotal+processing
                // and Tixello eats the 2% loss.
                $organizer = $activity->organizer ?? null;
                $organizerRate = $organizer
                    ? (float) $organizer->getEffectiveCommissionRate()
                    : 0.0;
                $commissionRate = $variant->commission_rate !== null
                    ? (float) $variant->commission_rate
                    : $organizerRate;
                $commissionMode = $organizer
                    ? $organizer->getEffectiveCommissionMode()
                    : 'included';
                $lineCommission = round($lineTotal * $commissionRate / 100, 2);
                $totalCommission += $lineCommission;
                // added_on_top → customer pays this on top of the ticket
                // price. included → it's baked into the ticket price and
                // doesn't change the customer-facing total. We track the
                // "added on top" sum SEPARATELY (not by mutating $subtotal)
                // so the order.subtotal column stays the pure ticket-value
                // base — reports + payout math read it as such.
                if ($commissionMode === 'added_on_top') {
                    $commissionAddedOnTop += $lineCommission;
                }

                $organizerIds[$activity->marketplace_organizer_id ?? 0] = true;
                if (!$primaryActivity) {
                    $primaryActivity    = $activity;
                    $primaryOrganizerId = $activity->marketplace_organizer_id;
                }

                $variantNameRo = is_array($variant->name)
                    ? ($variant->name['ro'] ?? $variant->name['en'] ?? array_values($variant->name)[0] ?? 'Bilet')
                    : ($variant->name ?? 'Bilet');
                $activityTitleRo = is_array($activity->title)
                    ? ($activity->title['ro'] ?? $activity->title['en'] ?? array_values($activity->title)[0] ?? 'Activitate')
                    : ($activity->title ?? 'Activitate');

                $stagedBookings[] = [
                    'activity'           => $activity,
                    'variant'            => $variant,
                    'booking_date'       => $item['booking_date'],
                    'slot_start_time'    => $item['slot_start_time'],
                    'slot_end_time'      => $item['slot_end_time'],
                    'participants_count' => $participants,
                    'slot_consumes'      => $slotConsumes,
                    'unit_price'         => $unitPrice,
                    'line_total'         => $lineTotal,
                    'commission'         => $lineCommission,
                    'commission_rate'    => $commissionRate,
                    'commission_mode'    => $commissionMode,
                    'variant_name'       => $variantNameRo,
                    'activity_title'     => $activityTitleRo,
                ];
            }

            // Processing fee snapshot. Kill switch = NULL payment_fees on
            // marketplace_clients leaves fee_cents=0 (no impact on RON total).
            //
            // Customer-facing total starts with ticket subtotal + any
            // "added_on_top" commission. Processing fee (computed below) is
            // calculated AGAINST this combined value so the % component
            // covers the commission too — matches the cart/checkout summary
            // labels customers saw on /finalizare.
            $orderTotal     = $subtotal + $commissionAddedOnTop;
            $processingFee  = [
                'fee_cents'        => 0,
                'pass_to_customer' => false,
                'provider'         => null,
                'percent_rate'     => null,
                'fixed_cents'      => null,
                'active'           => false,
            ];
            $isCardPayment  = in_array($request->input('payment_method', 'card'), ['card', 'card_cultural'], true);
            if (!$isTestOrder && $orderTotal > 0 && $isCardPayment) {
                $calc       = app(\App\Services\Payments\ProcessingFeeCalculator::class);
                $cents      = (int) round($orderTotal * 100);
                $organizer  = $primaryOrganizerId
                    ? \App\Models\MarketplaceOrganizer::find($primaryOrganizerId)
                    : null;
                $processingFee = $calc->compute($client, $organizer, 'stripe', $cents);
                if ($processingFee['pass_to_customer'] && $processingFee['fee_cents'] > 0) {
                    $orderTotal += $processingFee['fee_cents'] / 100;
                }
            }

            $currency        = $client->currency ?? 'RON';
            $isMultiOrganizer = count($organizerIds) > 1;
            $isFreeOrder     = !$isTestOrder && $orderTotal <= 0;
            $isAutoConfirmed = $isTestOrder || $isFreeOrder;

            $order = Order::create([
                'marketplace_client_id'    => $client->id,
                'marketplace_organizer_id' => $isMultiOrganizer ? null : $primaryOrganizerId,
                'tenant_id'                => null,
                'marketplace_customer_id'  => $customer->id,
                'event_id'                 => null,
                'marketplace_event_id'     => null,
                'order_number'             => ($isTestOrder ? 'TEST-' : 'ACT-') . strtoupper(Str::random(8)),
                'status'                   => $isAutoConfirmed ? 'completed' : 'pending',
                'payment_status'           => $isTestOrder ? 'test' : ($isFreeOrder ? 'free' : 'pending'),
                'subtotal'                 => $isTestOrder ? 0 : $subtotal,
                'discount_amount'          => 0,
                'commission_rate'          => 0, // commission tracked per-line in meta; aggregate not needed for activities v1
                'commission_amount'        => $isTestOrder ? 0 : $totalCommission,
                'total'                    => $isTestOrder ? 0 : $orderTotal,
                'processing_fee_cents'     => $isTestOrder ? 0 : (int) ($processingFee['fee_cents'] ?? 0),
                'processing_fee_passed'    => (bool) ($processingFee['pass_to_customer'] ?? false),
                'processing_fee_provider'  => $processingFee['provider'] ?? null,
                'processing_fee_percent_rate' => $processingFee['percent_rate'] ?? null,
                'processing_fee_fixed_cents'  => $processingFee['fixed_cents'] ?? null,
                'currency'                 => $currency,
                'source'                   => $isTestOrder ? 'test_order' : 'marketplace_activity',
                'customer_email'           => $customer->email,
                'customer_name'            => $customer->first_name . ' ' . $customer->last_name,
                'customer_phone'           => $customer->phone,
                'expires_at'               => $isAutoConfirmed ? null : now()->addMinutes(15),
                'paid_at'                  => $isAutoConfirmed ? now() : null,
                'meta' => [
                    'beneficiaries'      => $validated['beneficiaries'] ?? [],
                    'ip_address'         => $request->ip(),
                    'user_agent'         => $request->userAgent(),
                    'payment_method'     => $isTestOrder ? 'test' : $request->input('payment_method', 'card'),
                    'order_type'         => 'activity',
                    'activity_ids'       => array_unique(array_map(fn ($b) => $b['activity']->id, $stagedBookings)),
                    'multi_organizer'    => $isMultiOrganizer,
                    'commission_details' => array_map(fn ($b) => [
                        'activity'          => $b['activity_title'],
                        'variant'           => $b['variant_name'],
                        'participants'      => $b['participants_count'],
                        'unit_price'        => $b['unit_price'],
                        'line_total'        => $b['line_total'],
                        'commission'        => $b['commission'],
                        'commission_rate'   => $b['commission_rate'],
                        'commission_mode'   => $b['commission_mode'],
                    ], $stagedBookings),
                    // Aggregate split so reports + the admin order view can
                    // distinguish "Commission already in ticket price"
                    // (included) from "Commission added on top of the
                    // ticket price" (added_on_top). Total = subtotal +
                    // commission_added_on_top + processing_fee.
                    'commission_added_on_top' => $commissionAddedOnTop,
                ],
            ]);

            // Create bookings + order items + pending tickets.
            // Bookings start in pending_payment with held_until = now+5min so
            // the slot is reserved while the user is on the payment page.
            // ActivityBookingOrderObserver flips them to paid + clears the
            // hold when the order moves to paid/confirmed/completed.
            $heldUntil    = $isAutoConfirmed ? null : now()->addMinutes(5);
            $startStatus  = $isAutoConfirmed ? ActivityBooking::STATUS_PAID : ActivityBooking::STATUS_PENDING_PAYMENT;
            $ticketStatus = $isAutoConfirmed ? 'valid' : 'pending';
            $ticketIndex  = 0;

            foreach ($stagedBookings as $idx => $b) {
                $orderItem = $order->items()->create([
                    'ticket_type_id' => null,
                    'performance_id' => null,
                    'name'           => sprintf('%s — %s', $b['activity_title'], $b['variant_name']),
                    'quantity'       => $b['participants_count'],
                    'unit_price'     => $b['unit_price'],
                    'total'          => $b['line_total'],
                    'meta' => [
                        'activity_id'        => $b['activity']->id,
                        'variant_id'         => $b['variant']->id,
                        'booking_date'       => $b['booking_date'],
                        'slot_start_time'    => $b['slot_start_time'],
                        'slot_end_time'      => $b['slot_end_time'],
                        'participants_count' => $b['participants_count'],
                        'capacity_share'     => (int) ($b['variant']->capacity_share ?? 1),
                        'order_type'         => 'activity',
                    ],
                ]);

                $booking = ActivityBooking::create([
                    'marketplace_client_id'   => $client->id,
                    'activity_id'             => $b['activity']->id,
                    'marketplace_customer_id' => $customer->id,
                    'order_id'                => $order->id,
                    'booking_date'            => $b['booking_date'],
                    'slot_start_time'         => $b['slot_start_time'],
                    'slot_end_time'           => $b['slot_end_time'],
                    'participants_count'      => $b['slot_consumes'],
                    'status'                  => $startStatus,
                    'total_cents'             => (int) round($b['line_total'] * 100),
                    'commission_cents'        => (int) round($b['commission'] * 100),
                    'currency'                => $currency,
                    'held_until'              => $heldUntil,
                ]);

                // Emit one ticket per participant (NOT capacity_share — capacity_share
                // controls slot consumption, not ticket count). Group variants
                // (capacity_share>1) still emit `participants_count` tickets.
                for ($i = 0; $i < $b['participants_count']; $i++) {
                    $beneficiary = $validated['beneficiaries'][$ticketIndex] ?? null;

                    Ticket::create([
                        'marketplace_client_id'    => $client->id,
                        'tenant_id'                => null,
                        'order_id'                 => $order->id,
                        'order_item_id'            => $orderItem->id,
                        'event_id'                 => null,
                        'marketplace_event_id'     => null,
                        'ticket_type_id'           => null,
                        'marketplace_ticket_type_id' => null,
                        'activity_booking_id'      => $booking->id,
                        'performance_id'           => null,
                        'marketplace_customer_id'  => $customer->id,
                        'code'                     => strtoupper(Str::random(8)),
                        'barcode'                  => Str::uuid()->toString(),
                        'status'                   => $ticketStatus,
                        'price'                    => $isTestOrder ? 0 : $b['unit_price'],
                        'attendee_name'            => $beneficiary['name'] ?? null,
                        'attendee_email'           => $beneficiary['email'] ?? null,
                        'meta' => [
                            'activity_id'     => $b['activity']->id,
                            'variant_id'      => $b['variant']->id,
                            'booking_date'    => $b['booking_date'],
                            'slot_start_time' => $b['slot_start_time'],
                            'slot_end_time'   => $b['slot_end_time'],
                        ],
                    ]);

                    $ticketIndex++;
                }
            }

            DB::commit();

            // Auto-account email (same pathway as event checkout).
            if ($autoCreatedPassword) {
                try {
                    $setPasswordToken = \Illuminate\Support\Str::random(64);
                    DB::table('marketplace_password_resets')
                        ->where('email', $customer->email)
                        ->where('type', 'customer')
                        ->where('marketplace_client_id', $client->id)
                        ->delete();
                    DB::table('marketplace_password_resets')->insert([
                        'email'                 => $customer->email,
                        'type'                  => 'customer',
                        'marketplace_client_id' => $client->id,
                        'token'                 => Hash::make($setPasswordToken),
                        'created_at'            => now(),
                    ]);
                    $this->sendAccountCreatedEmail($client, $customer, $setPasswordToken);
                } catch (\Exception $e) {
                    Log::channel('marketplace')->warning('Activity checkout: account email failed', [
                        'customer_id' => $customer->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $primaryTitle = $primaryActivity
                ? (is_array($primaryActivity->title)
                    ? ($primaryActivity->title['ro'] ?? $primaryActivity->title['en'] ?? array_values($primaryActivity->title)[0] ?? 'Activitate')
                    : $primaryActivity->title)
                : 'Activitate';

            Log::channel('marketplace')->info('Activity checkout completed', [
                'client_id'    => $client->id,
                'customer_id'  => $customer->id,
                'order_id'     => $order->id,
                'booking_count' => count($stagedBookings),
                'total'        => (float) $order->total,
            ]);

            return $this->success([
                'orders' => [[
                    'id'           => $order->id,
                    'order_number' => $order->order_number,
                    'event'        => [
                        'id'   => null,
                        'name' => $primaryTitle . (count($stagedBookings) > 1 ? ' + ' . (count($stagedBookings) - 1) . ' alte' : ''),
                    ],
                    'subtotal'     => (float) $order->subtotal,
                    'discount'     => 0.0,
                    'insurance'    => 0.0,
                    'total'        => (float) $order->total,
                    'currency'     => $order->currency,
                    'expires_at'   => $order->expires_at?->toIso8601String(),
                ]],
                'customer' => [
                    'id'          => $customer->id,
                    'email'       => $customer->email,
                    'name'        => $customer->first_name . ' ' . $customer->last_name,
                    'has_account' => $customer->password !== null,
                ],
                'payment_required' => (float) $order->total > 0,
            ], 'Checkout successful', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('marketplace')->error('Activity checkout failed', [
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);

            return $this->error('Checkout failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Lightweight pre-transaction validation for activity cart items. The
     * authoritative re-check (with row locks) happens inside the checkout
     * transaction; this is the "fail fast and tell the user why" pass.
     */
    protected function validateActivityCartItems(array $items): array
    {
        $errors = [];

        foreach ($items as $key => $item) {
            $activityId = $item['activity_id']   ?? null;
            $variantId  = $item['variant_id']    ?? null;
            $bookingDate    = $item['booking_date']    ?? null;
            $slotStartTime  = $item['slot_start_time'] ?? null;
            $participants   = (int) ($item['participants_count'] ?? 0);

            if (!$activityId || !$variantId || !$bookingDate || !$slotStartTime || $participants <= 0) {
                $errors[$key] = 'Rezervarea este incompletă';
                continue;
            }

            $activity = Activity::with(['variants', 'schedules', 'scheduleExceptions'])
                ->find($activityId);
            if (!$activity || !$activity->is_published) {
                $errors[$key] = 'Activitatea nu mai este disponibilă';
                continue;
            }

            $variant = $activity->variants->firstWhere('id', $variantId);
            if (!$variant || !$variant->is_active) {
                $errors[$key] = 'Varianta selectată nu mai este disponibilă';
                continue;
            }

            // min_per_order / max_per_order respected at variant level
            if ($variant->min_per_order && $participants < $variant->min_per_order) {
                $errors[$key] = sprintf('Minim %d persoane pentru această variantă', $variant->min_per_order);
                continue;
            }
            if ($variant->max_per_order && $participants > $variant->max_per_order) {
                $errors[$key] = sprintf('Maxim %d persoane pentru această variantă', $variant->max_per_order);
                continue;
            }

            // Slot computation via SlotResolver — same source of truth as the
            // public availability API, so the customer's UI and our backend
            // can't disagree on whether a slot is bookable.
            try {
                $date = CarbonImmutable::parse($bookingDate);
            } catch (\Throwable $e) {
                $errors[$key] = 'Data rezervării este invalidă';
                continue;
            }

            $slots = SlotResolver::slotsFor($activity, $date);
            $startStr = strlen($slotStartTime) === 5 ? $slotStartTime . ':00' : $slotStartTime;
            $slot = $slots->firstWhere('start_time', $startStr);

            if (!$slot) {
                $errors[$key] = 'Slotul ales nu mai există în programul activității';
                continue;
            }
            if (!$slot['is_bookable']) {
                $errors[$key] = match ($slot['unavailable_reason']) {
                    'past'              => 'Slotul a trecut deja',
                    'too_far_in_future' => 'Slotul este prea departe în viitor pentru rezervare',
                    'lead_time'         => 'Slotul este prea aproape — trebuie rezervat cu mai mult timp înainte',
                    'full'              => 'Slotul este complet ocupat',
                    default             => 'Slotul nu poate fi rezervat',
                };
                continue;
            }

            $capacityShare = max(1, (int) ($variant->capacity_share ?? 1));
            $slotConsumes  = $participants * $capacityShare;
            if ($slotConsumes > $slot['capacity_remaining']) {
                $errors[$key] = sprintf(
                    'Slotul mai are doar %d locuri disponibile',
                    (int) $slot['capacity_remaining']
                );
            }
        }

        return $errors;
    }

    /**
     * Get checkout summary (preview before checkout)
     */
    public function summary(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty', 400);
        }

        // Group items by event
        $itemsByEvent = collect($cart->items)->groupBy('event_id');
        $summary = [];

        foreach ($itemsByEvent as $eventId => $items) {
            $event = MarketplaceEvent::with('organizer:id,name')->find($eventId);
            if (!$event) {
                continue;
            }

            $eventSubtotal = $items->sum(fn($item) => $item['price'] * $item['quantity']);

            $summary[] = [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->starts_at->toIso8601String(),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                    'organizer' => $event->organizer?->name,
                ],
                'items' => $items->map(fn($item) => [
                    'ticket_type' => $item['ticket_type_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity'],
                ])->values(),
                'subtotal' => $eventSubtotal,
            ];
        }

        // F3 — preview processing fee when the marketplace has opted in.
        // Kill switch: payment_fees=NULL → calculator returns zero → no fee
        // in response → frontend renders identically to legacy.
        $paymentMethod = $request->input('payment_method', 'card');
        $isCardPayment = in_array($paymentMethod, ['card', 'card_cultural'], true);
        $processingFeePayload = null;
        if ($isCardPayment) {
            $calc = app(\App\Services\Payments\ProcessingFeeCalculator::class);
            $feeBaseCents = (int) round(((float) $cart->total) * 100);
            $fee = $calc->compute($client, null, 'stripe', $feeBaseCents);
            if ($fee['active'] && $fee['pass_to_customer'] && $fee['fee_cents'] > 0) {
                $processingFeePayload = [
                    'amount'       => round($fee['fee_cents'] / 100, 2),
                    'currency'     => $cart->currency,
                    'provider'     => $fee['provider'],
                    'percent_rate' => $fee['percent_rate'],
                    'fixed'        => round(((int) $fee['fixed_cents']) / 100, 2),
                    'label'        => 'Taxă procesare card',
                ];
            }
        }
        $cartTotal = (float) $cart->total + (float) ($processingFeePayload['amount'] ?? 0);

        return $this->success([
            'summary' => $summary,
            'cart' => [
                'subtotal' => (float) $cart->subtotal,
                'discount' => (float) $cart->discount,
                'processing_fee' => $processingFeePayload,
                'total' => $cartTotal,
                'currency' => $cart->currency,
                'promo_code' => $cart->promo_code ? $cart->promo_code['code'] : null,
            ],
            'expires_at' => $cart->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Validate cart items for checkout (accepts array of items)
     * Note: Marketplace sells tenant events, so we use TicketType model (not MarketplaceTicketType)
     */
    protected function validateCartItemsForCheckout(array $items, bool $isTestOrder = false): array
    {
        $errors = [];

        foreach ($items as $key => $item) {
            $ticketTypeId = $item['ticket_type_id'] ?? null;
            $itemEventId = $item['event_id'] ?? null;

            if (!$ticketTypeId) {
                Log::channel('marketplace')->debug('Validation: missing ticket_type_id', ['item' => $item]);
                $errors[$key] = 'Invalid item: missing ticket type';
                continue;
            }

            // Determine which model by checking event context
            $mktTicketType = null;
            $ticketType = null;

            // Check MarketplaceTicketType if event_id matches a MarketplaceEvent
            if ($itemEventId) {
                $mktTicketType = MarketplaceTicketType::where('id', $ticketTypeId)
                    ->where('marketplace_event_id', $itemEventId)
                    ->first();
            }

            if ($mktTicketType) {
                if ($mktTicketType->status === 'sold_out') {
                    $errors[$key] = 'Ticket type is sold out';
                    continue;
                }
                if ($mktTicketType->status !== 'on_sale' && $mktTicketType->status !== 'active') {
                    $errors[$key] = "Ticket type is not available (status: {$mktTicketType->status})";
                    continue;
                }

                $available = $mktTicketType->quantity === null
                    ? PHP_INT_MAX
                    : max(0, $mktTicketType->quantity - ($mktTicketType->quantity_sold ?? 0) - ($mktTicketType->quantity_reserved ?? 0));

                $quantity = $item['quantity'] ?? 1;
                if ($quantity > $available) {
                    $errors[$key] = "Only {$available} tickets available";
                }
                continue;
            }

            // Fallback: try TicketType (tenant ticket types)
            $ticketType = TicketType::with('event')
                ->where('id', $ticketTypeId)
                ->when($itemEventId, fn ($q) => $q->where('event_id', $itemEventId))
                ->first();

            if (!$ticketType) {
                Log::channel('marketplace')->debug('Validation: ticket type not found', ['ticket_type_id' => $ticketTypeId]);
                $errors[$key] = 'Ticket type no longer exists';
                continue;
            }

            if ($ticketType->status !== 'active') {
                $errors[$key] = "Ticket type is not available (status: {$ticketType->status})";
                continue;
            }

            if (!$ticketType->event || (!$ticketType->event->is_published && !$isTestOrder)) {
                $errors[$key] = "Event is no longer available";
                continue;
            }

            $ownAvailable = ($ticketType->quota_total === null || $ticketType->quota_total < 0)
                ? PHP_INT_MAX
                : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

            $available = $ownAvailable;
            $event = $ticketType->event;
            if ($event && $event->general_quota !== null && !$ticketType->is_independent_stock) {
                $soldNonIndep = $event->ticketTypes()
                    ->where('is_independent_stock', false)
                    ->sum('quota_sold');
                $poolRemaining = max(0, $event->general_quota - (int) $soldNonIndep);
                $available = min($ownAvailable, $poolRemaining);
            }

            $quantity = $item['quantity'] ?? 1;
            if ($quantity > $available) {
                $errors[$key] = "Only {$available} tickets available";
            }
        }

        return $errors;
    }

    /**
     * Validate cart items for checkout (accepts MarketplaceCart object)
     * @deprecated Use validateCartItemsForCheckout instead
     */
    protected function validateCartForCheckout(MarketplaceCart $cart): array
    {
        return $this->validateCartItemsForCheckout($cart->items ?? []);
    }

    /**
     * Get session ID from request
     */
    protected function getSessionId(Request $request): string
    {
        // Try header first
        if ($sessionId = $request->header('X-Session-ID')) {
            return $sessionId;
        }

        // Try cookie
        if ($sessionId = $request->cookie('session_id')) {
            return $sessionId;
        }

        // Try Laravel session (may not be available on API routes)
        try {
            if ($request->hasSession() && ($sessionId = $request->session()->getId())) {
                return $sessionId;
            }
        } catch (\Exception $e) {
            // Session not available, fall through to fallback
        }

        // Fallback: generate deterministic ID from IP and user agent
        return md5($request->ip() . $request->userAgent());
    }

    /**
     * Send account created email via marketplace mail transport.
     */
    protected function sendAccountCreatedEmail($client, MarketplaceCustomer $customer, string $setPasswordToken): void
    {
        $siteName = $client->name ?? 'bilete.online';
        $domain = $client->domain ? rtrim($client->domain, '/') : config('app.url');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $setPasswordUrl = $domain . '/reset-password?' . http_build_query([
            'token' => $setPasswordToken,
            'email' => $customer->email,
        ]);

        $firstName = $customer->first_name ?: 'Client';
        $subject = "Contul tău pe {$siteName}";

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Bine ai venit!</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut ' . htmlspecialchars($firstName) . ',</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Ți-am creat automat un cont pe <strong>' . htmlspecialchars($siteName) . '</strong> folosind datele de la ultima ta comandă.</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 8px"><strong>Email:</strong> ' . htmlspecialchars($customer->email) . '</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 24px">Setează-ți o parolă pentru a-ți activa contul:</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($setPasswordUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Setează parola</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul expiră în 60 de minute.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $this->sendMarketplaceEmail($client, $customer->email, $firstName, $subject, $html, [
            'marketplace_customer_id' => $customer->id,
            'template_slug' => 'account_created',
        ]);
    }

    /**
     * Check if ticket type stock dropped below alert threshold and send notification.
     */
    protected function checkLowStockAlert(TicketType $ticketType): void
    {
        try {
            $event = $ticketType->event;
            if (!$event) return;

            $client = $event->marketplaceClient ?? \App\Models\MarketplaceClient::find($event->marketplace_client_id);
            if (!$client) return;

            $settings = $client->settings ?? [];
            $threshold = $settings['stock_alert_threshold'] ?? null;
            $alertEmail = $settings['stock_alert_email'] ?? null;

            if (!$threshold || !$alertEmail) return;

            // Calculate remaining
            $remaining = $event->getAvailableForTicketType($ticketType);

            // Only alert when crossing the threshold (remaining <= threshold AND remaining + qty_just_sold > threshold)
            if ($remaining > (int) $threshold) return;

            $ttName = is_array($ticketType->name) ? ($ticketType->name['ro'] ?? $ticketType->name['en'] ?? '') : $ticketType->name;
            $eventName = is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? '') : ($event->title ?? '');
            $organizer = $event->marketplaceOrganizer;

            // Try to use email template
            $template = \App\Models\MarketplaceEmailTemplate::where('marketplace_client_id', $client->id)
                ->where('slug', 'stock_low_alert')
                ->where('is_active', true)
                ->first();

            $venue = $event->venue;
            $venueName = $venue ? (is_array($venue->name) ? ($venue->name['ro'] ?? $venue->name['en'] ?? '') : $venue->name) : '';
            $venueCity = $venue?->city ?? '';
            $eventDate = $event->event_date?->format('d.m.Y') ?? '';

            $variables = [
                'ticket_type' => $ttName,
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'venue_name' => $venueName,
                'venue_city' => $venueCity,
                'remaining_stock' => $remaining,
                'organizer_name' => $organizer?->name ?? $organizer?->company_name ?? 'Organizator',
                'organizer_email' => $organizer?->email ?? '',
                'admin_url' => "https://{$client->domain}/marketplace/events/{$event->id}/edit?tab=bilete",
            ];

            if ($template) {
                $subject = $template->processSubject($variables);
                $html = $template->processBody($variables);
            } else {
                $subject = "⚠ Alertă stoc: {$ttName} — {$eventName}";
                $html = "<div style='font-family:sans-serif;font-size:14px;color:#333;'>"
                    . "Stocul pentru <strong>{$ttName}</strong> ({$eventName}) a scăzut la <strong>{$remaining}</strong> bilete."
                    . "</div>";
            }

            // Build recipient list: admin alert email + optionally organizer
            $recipients = array_filter([$alertEmail]);

            // Check if organizer should also be notified
            $orgNotifications = $event->organizer_notifications ?? [];
            if (!empty($orgNotifications['stock_low_alert']) && $organizer?->email) {
                $recipients[] = $organizer->email;
            }

            $recipients = array_unique($recipients);

            // Route through marketplace transport so the slug is auto-routed to the
            // transactional provider (stock_low_alert is in EmailRouting whitelist).
            foreach ($recipients as $recipient) {
                \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                    $client,
                    $recipient,
                    '',
                    $subject,
                    $html,
                    [
                        'template_slug' => 'stock_low_alert',
                        'metadata' => [
                            'ticket_type_id' => $ticketType->id,
                            'event_id' => $event->id,
                            'remaining' => $remaining,
                            'threshold' => $threshold,
                        ],
                    ]
                );
            }

            \Log::info('Low stock alert sent', [
                'ticket_type_id' => $ticketType->id,
                'event_id' => $event->id,
                'remaining' => $remaining,
                'threshold' => $threshold,
                'recipients' => $recipients,
                'email' => $alertEmail,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send low stock alert', ['error' => $e->getMessage()]);
        }
    }
}
