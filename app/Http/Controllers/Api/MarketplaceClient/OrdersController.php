<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Order;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\PosTicketClaim;
use App\Models\MarketplaceTransaction;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Services\MarketplaceWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrdersController extends BaseController
{
    /**
     * Create a new order (reserve tickets)
     */
    public function create(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'tickets' => 'required|array|min:1',
            'tickets.*.ticket_type_id' => 'required|integer|exists:ticket_types,id',
            'tickets.*.quantity' => 'required|integer|min:1|max:20',
            'tickets.*.performance_id' => 'nullable|integer|exists:performances,id',
            'seat_uids' => 'nullable|array',
            'seat_uids.*' => 'string|max:32',
            'customer' => 'required|array',
            'customer.email' => 'required|email',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'required|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
        ]);

        $event = Event::find($request->event_id);

        // Check for valid preview token (allows orders on unpublished events)
        $previewToken = $request->input('preview_token');
        $isTestOrder = $previewToken && $this->validatePreviewToken($previewToken, (int) $request->event_id);

        if (!$event || ($event->status !== 'published' && !$isTestOrder)) {
            return $this->error('Event not available', 400);
        }

        // Derive tenant_id: prefer event's, then fallback chain
        $tenantId = $event->tenant_id;
        if (!$tenantId) {
            // Try other events from same organizer that have tenant_id
            if ($event->marketplace_organizer_id) {
                $tenantId = Event::where('marketplace_organizer_id', $event->marketplace_organizer_id)
                    ->whereNotNull('tenant_id')
                    ->value('tenant_id');
            }
        }
        if (!$tenantId) {
            // Try the client's tenant list (active or any)
            $tenantId = $client->activeTenants()->first()?->id
                ?? $client->tenants()->first()?->id;
        }
        if (!$tenantId) {
            // Last resort: find any tenant in the system (single-tenant setups)
            $tenantId = \App\Models\Tenant::first()?->id;
        }
        if (!$tenantId) {
            return $this->error('No tenant configured. Please contact support.', 400);
        }

        if (!$client->canSellForTenant($tenantId)) {
            return $this->error('Not authorized to sell tickets for this event', 403);
        }

        // Find or create customer BEFORE the transaction.
        // This avoids PostgreSQL/PgBouncer issues where a failed INSERT
        // (unique constraint) inside a transaction aborts it permanently.
        $customerEmail = strtolower(trim($request->input('customer.email')));
        $customer = Customer::where('email', $customerEmail)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($customer) {
            $customer->update([
                'first_name' => $request->input('customer.first_name'),
                'last_name' => $request->input('customer.last_name'),
                'phone' => $request->input('customer.phone'),
            ]);
        } else {
            $customer = Customer::create([
                'email' => $customerEmail,
                'tenant_id' => $tenantId,
                'primary_tenant_id' => $tenantId,
                'first_name' => $request->input('customer.first_name'),
                'last_name' => $request->input('customer.last_name'),
                'phone' => $request->input('customer.phone'),
            ]);
        }

        // Ensure customer-tenant pivot (safe INSERT ... ON CONFLICT DO NOTHING)
        DB::table('customer_tenant')->insertOrIgnore([
            'customer_id' => $customer->id,
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::beginTransaction();

            // Calculate totals and validate availability
            $orderItems = [];
            $subtotal = 0;
            $commission = $client->getCommissionForTenant($tenantId);

            foreach ($request->tickets as $ticketRequest) {
                $ticketType = TicketType::where('id', $ticketRequest['ticket_type_id'])
                    ->where('event_id', $event->id)
                    ->whereIn('status', ['active', 'on_sale', 'published'])
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType) {
                    throw new \Exception("Ticket type {$ticketRequest['ticket_type_id']} not available");
                }

                $quantity = (int) $ticketRequest['quantity'];

                if ($ticketType->available_quantity < $quantity) {
                    throw new \Exception("Not enough tickets available for {$ticketType->name}");
                }

                if ($ticketType->max_per_order && $quantity > $ticketType->max_per_order) {
                    throw new \Exception("Maximum {$ticketType->max_per_order} tickets per order for {$ticketType->name}");
                }

                // Resolve performance and potential price override
                $perfId = $ticketRequest['performance_id'] ?? null;
                $isInvitation = (bool) $request->input('is_invitation', false);
                $unitPrice = $isInvitation ? 0 : ($ticketType->display_price ?? ($ticketType->price_cents / 100) ?? 0);

                // Apply per-performance price override if available
                if ($perfId && !$isInvitation) {
                    $performance = \App\Models\Performance::where('id', $perfId)
                        ->where('event_id', $event->id)
                        ->first();
                    if ($performance) {
                        $priceOverride = $performance->getEffectivePrice($ticketType);
                        if ($priceOverride !== null) {
                            $unitPrice = $priceOverride / 100;
                        }
                    }
                }

                $itemTotal = $unitPrice * $quantity;
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'ticket_type' => $ticketType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $itemTotal,
                    'performance_id' => $perfId,
                ];

                // Reserve tickets (increment quota_sold; available_quantity is computed)
                $ticketType->increment('quota_sold', $quantity);
            }

            // Calculate commission
            // POS/mobile app orders (source=pos_app) always use total = subtotal, regardless of commission_mode.
            // The ticket price IS the price the customer pays at the door — commission is never added on top.
            // For online orders: respect commission_mode (on_top adds to price; included deducts from organizer payout).
            $posSource = $request->input('source', 'marketplace');
            $commissionMode = $event->getEffectiveCommissionMode();
            $commissionAmount = round($subtotal * ($commission / 100), 2);
            $isOnTop = in_array($commissionMode, ['on_top', 'added_on_top']) && $posSource !== 'pos_app';
            $total = $isOnTop ? $subtotal + $commissionAmount : $subtotal;

            // Create order — disable activity logging and ticket sync inside this transaction
            // to avoid any side-effect queries that could abort the PostgreSQL transaction.
            // Insert order directly via DB to avoid all model events (OrderObserver,
            // LogsActivity, saved callback) that can fail and abort PostgreSQL transaction.
            $orderId = DB::table('orders')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'customer_id' => $customer->id,
                'order_number' => 'MPC-' . strtoupper(Str::random(8)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'commission_rate' => $commission,
                'commission_amount' => $commissionAmount,
                'total' => $total,
                'currency' => 'RON',
                'source' => $request->input('source', 'marketplace'),
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $event->marketplace_organizer_id,
                'customer_email' => $customer->email,
                'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                'customer_phone' => $customer->phone,
                'expires_at' => now()->addMinutes(15),
                'meta' => json_encode([
                    'marketplace_client' => $client->name,
                    'ip_address' => $request->ip(),
                    'sold_by' => $request->input('sold_by'),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $order = Order::find($orderId);

            // Create order items and tickets
            foreach ($orderItems as $item) {
                $orderItem = $order->items()->create([
                    'ticket_type_id' => $item['ticket_type']->id,
                    'performance_id' => $item['performance_id'] ?? null,
                    'name' => $item['ticket_type']->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                ]);

                // Resolve ticket series prefix and next number from series_start
                $ticketType = $item['ticket_type'];
                $seriesPrefix = null;
                $seriesPadLength = 5;
                $nextSeriesNum = null;

                if ($ticketType->series_start) {
                    // Extract prefix and starting number from series_start (e.g. "AMB-5-GA-00001")
                    if (preg_match('/^(.+?)(\d+)$/', $ticketType->series_start, $m)) {
                        $seriesPrefix = $m[1]; // e.g. "AMB-5-GA-"
                        $seriesPadLength = strlen($m[2]); // e.g. 5
                        $startNum = (int) $m[2]; // e.g. 1

                        // Count existing tickets for this ticket_type to determine next number
                        $existingCount = Ticket::where('ticket_type_id', $ticketType->id)
                            ->whereNotNull('meta->ticket_series')
                            ->count();
                        $nextSeriesNum = $startNum + $existingCount;
                    }
                }

                // Create pending tickets
                for ($i = 0; $i < $item['quantity']; $i++) {
                    $ticketMeta = [];

                    // Generate ticket series number
                    if ($seriesPrefix !== null && $nextSeriesNum !== null) {
                        $ticketMeta['ticket_series'] = $seriesPrefix . str_pad($nextSeriesNum, $seriesPadLength, '0', STR_PAD_LEFT);
                        $nextSeriesNum++;
                    }

                    Ticket::create([
                        'tenant_id' => $tenantId,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'event_id' => $event->id,
                        'ticket_type_id' => $item['ticket_type']->id,
                        'performance_id' => $item['performance_id'] ?? null,
                        'customer_id' => $customer->id,
                        'code' => strtoupper(Str::random(12)),
                        'barcode' => Str::uuid()->toString(),
                        'status' => 'pending',
                        'price' => $item['unit_price'],
                        'meta' => !empty($ticketMeta) ? $ticketMeta : null,
                    ]);
                }
            }

            // Handle seated ticket sales - mark selected seats as sold
            $seatUids = $request->input('seat_uids', []);
            if (!empty($seatUids)) {
                $layout = EventSeatingLayout::where('event_id', $event->id)
                    ->published()
                    ->latest('published_at')
                    ->first();

                if (!$layout) {
                    throw new \Exception('No seating layout found for this event');
                }

                // Validate and lock all requested seats atomically
                $seats = EventSeat::where('event_seating_id', $layout->id)
                    ->whereIn('seat_uid', $seatUids)
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== count($seatUids)) {
                    $found = $seats->pluck('seat_uid')->toArray();
                    $missing = array_diff($seatUids, $found);
                    throw new \Exception('Seats not found: ' . implode(', ', $missing));
                }

                $unavailable = $seats->filter(fn($s) => $s->status !== 'available' && $s->status !== 'held');
                if ($unavailable->isNotEmpty()) {
                    $labels = $unavailable->map(fn($s) => "{$s->section_name} {$s->row_label}-{$s->seat_label}")->join(', ');
                    throw new \Exception("Locuri indisponibile: {$labels}");
                }

                // Mark all seats as sold
                EventSeat::where('event_seating_id', $layout->id)
                    ->whereIn('seat_uid', $seatUids)
                    ->update([
                        'status' => 'sold',
                        'last_change_at' => now(),
                        'version' => DB::raw('version + 1'),
                    ]);

                // Clean up any seat holds for these seats
                \App\Models\Seating\SeatHold::where('event_seating_id', $layout->id)
                    ->whereIn('seat_uid', $seatUids)
                    ->delete();

                // Assign seat info to tickets (1:1 mapping seat_uid → ticket)
                $tickets = $order->tickets()->orderBy('id')->get();
                $seatIndex = 0;
                foreach ($tickets as $ticket) {
                    if ($seatIndex < count($seatUids)) {
                        $seat = $seats->firstWhere('seat_uid', $seatUids[$seatIndex]);
                        if ($seat) {
                            $ticket->update([
                                'seat_label' => $seat->section_name . ' ' . $seat->row_label . '-' . $seat->seat_label,
                                'meta' => array_merge($ticket->meta ?? [], [
                                    'seat_uid' => $seat->seat_uid,
                                    'event_seating_id' => $layout->id,
                                    'section_name' => $seat->section_name,
                                    'row_label' => $seat->row_label,
                                    'seat_number' => $seat->seat_label,
                                ]),
                            ]);
                        }
                        $seatIndex++;
                    }
                }

                // Store seated items in order meta
                $order->update([
                    'meta' => array_merge($order->meta ?? [], [
                        'seated_items' => [[
                            'event_seating_id' => $layout->id,
                            'seat_uids' => $seatUids,
                        ]],
                    ]),
                ]);
            }

            // Auto-confirm POS cash orders and invitations immediately.
            // Use raw DB update to avoid triggering OrderObserver::updated() which
            // calls trackPurchaseConversion inside the transaction. That method writes
            // to a UUID column with a non-UUID value, causing PostgreSQL to abort the
            // entire transaction (25P02 cascade).
            $paymentMethod = $request->input('payment_method');
            $source = $request->input('source', 'marketplace');
            $isInvitation = (bool) $request->input('is_invitation', false);
            if (($paymentMethod === 'cash' || $isInvitation) && $source === 'pos_app') {
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => 'confirmed',
                    'payment_status' => $isInvitation ? 'free' : 'paid',
                    'paid_at' => now(),
                    'meta' => json_encode(array_merge($order->meta ?? [], $isInvitation ? ['is_invitation' => true] : [])),
                    'updated_at' => now(),
                ]);
                // Mark tickets as valid
                DB::table('tickets')->where('order_id', $order->id)->update([
                    'status' => 'valid',
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            activity()->enableLogging();

            // Reload order to get updated status
            $order->refresh();

            Log::info('Marketplace order created', [
                'order_id' => $order->id,
                'marketplace_client_id' => $client->id,
                'tenant_id' => $tenantId,
                'total' => $total,
            ]);

            // Send webhook notification (async)
            dispatch(function () use ($client, $order) {
                app(MarketplaceWebhookService::class)->orderCreated($client, [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'currency' => $order->currency,
                ]);
            })->afterResponse();

            $isPosConfirmed = $paymentMethod === 'cash' && $source === 'pos_app';

            $responseData = [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'subtotal' => $order->subtotal,
                    'commission_amount' => $order->commission_amount,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'expires_at' => $order->expires_at?->toIso8601String(),
                ],
                'payment_url' => null,
            ];

            // Include ticket barcodes for POS confirmed orders
            if ($isPosConfirmed) {
                $order->load('tickets.ticketType');
                $responseData['tickets'] = $order->tickets->map(fn($t) => [
                    'id' => $t->id,
                    'barcode' => $t->barcode,
                    'code' => $t->code,
                    'ticket_type' => $t->ticketType?->name,
                    'status' => $t->status,
                    'ticket_series' => $t->meta['ticket_series'] ?? null,
                ]);
            }

            return $this->success($responseData, 'Order created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            activity()->enableLogging();

            Log::error('Failed to create marketplace order', [
                'marketplace_client_id' => $client->id,
                'event_id' => $request->event_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get order status
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::where(function ($q) use ($orderId) {
                if (is_numeric($orderId)) {
                    $q->where('id', (int) $orderId);
                } else {
                    $q->where('order_number', $orderId);
                }
            })
            ->where('marketplace_client_id', $client->id)
            ->with([
                'marketplaceEvent',
                'event.venue',
                'tickets.marketplaceTicketType',
                'tickets.ticketType',
            ])
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        // Build event data
        $eventData = null;
        if ($order->marketplaceEvent) {
            $eventData = [
                'id' => $order->marketplaceEvent->id,
                'name' => $order->marketplaceEvent->name,
                'slug' => $order->marketplaceEvent->slug,
                'date' => $order->marketplaceEvent->starts_at?->toIso8601String(),
                'doors_open' => $order->marketplaceEvent->doors_open_at?->toIso8601String(),
                'venue' => $order->marketplaceEvent->venue_name,
                'city' => $order->marketplaceEvent->venue_city,
                'image' => $order->marketplaceEvent->image_url,
            ];
        } elseif ($order->event) {
            $imageUrl = null;
            if ($order->event->featured_image) {
                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($order->event->featured_image);
            } elseif ($order->event->poster_url) {
                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($order->event->poster_url);
            }
            $eventTitle = is_array($order->event->title)
                ? ($order->event->title['ro'] ?? $order->event->title['en'] ?? reset($order->event->title))
                : $order->event->title;
            $eventData = [
                'id' => $order->event->id,
                'name' => $eventTitle,
                'slug' => $order->event->slug,
                'date' => $order->event->event_date?->toIso8601String(),
                'doors_open' => $order->event->door_time,
                'venue' => $order->event->venue?->name,
                'city' => $order->event->venue?->city,
                'image' => $imageUrl,
            ];
        }

        // Build items grouped by ticket type
        $items = $order->tickets->groupBy(function ($ticket) {
            return $ticket->marketplace_ticket_type_id ?? $ticket->ticket_type_id ?? 0;
        })->map(function ($tickets) {
            $first = $tickets->first();
            $ticketType = $first->marketplaceTicketType ?? $first->ticketType;
            $price = (float) ($first->price ?? $ticketType?->price ?? 0);
            return [
                'name' => $ticketType?->name ?? 'Bilet',
                'quantity' => $tickets->count(),
                'price' => $price,
                'total' => $price * $tickets->count(),
            ];
        })->values()->toArray();

        // Payment method display
        $paymentMethod = match($order->payment_processor) {
            'netopia', 'payment-netopia' => 'Card bancar (Netopia)',
            'stripe', 'payment-stripe' => 'Card bancar (Stripe)',
            'cash' => 'Numerar',
            default => $order->payment_processor ? ucfirst(str_replace(['_', '-'], ' ', $order->payment_processor)) : 'Card',
        };

        // Service fee calculation
        $discount = (float) ($order->discount_amount ?? $order->promo_discount ?? 0);
        $insuranceAmount = (float) ($order->meta['insurance_amount'] ?? 0);
        $serviceFee = max(0, (float) $order->total - (float) $order->subtotal + $discount - $insuranceAmount);

        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'service_fee' => number_format($serviceFee, 2, '.', ''),
                'insurance_amount' => number_format($insuranceAmount, 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'total' => number_format((float) $order->total, 2, '.', ''),
                'currency' => $order->currency ?? 'RON',
                'event' => $eventData,
                'items' => $items,
                'tickets' => $order->tickets->map(function ($ticket) {
                    $ticketType = $ticket->marketplaceTicketType ?? $ticket->ticketType;
                    $seatDetails = $ticket->getSeatDetails();
                    $seatData = null;
                    if ($seatDetails || $ticket->seat_label) {
                        $seatData = [
                            'label' => $ticket->seat_label,
                            'section_name' => $seatDetails['section_name'] ?? null,
                            'row_label' => $seatDetails['row_label'] ?? null,
                            'seat_number' => $seatDetails['seat_number'] ?? null,
                        ];
                    }
                    return [
                        'id' => $ticket->id,
                        'code' => $ticket->code,
                        'barcode' => $ticket->barcode,
                        'type' => $ticketType?->name,
                        'price' => (float) ($ticketType?->price ?? $ticket->price ?? 0),
                        'status' => $ticket->status,
                        'attendee_name' => $ticket->attendee_name,
                        'seat' => $seatData,
                        'has_insurance' => (bool) ($ticket->meta['has_insurance'] ?? false),
                        'ticket_series' => $ticket->meta['ticket_series'] ?? null,
                    ];
                }),
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'payment_method' => $paymentMethod,
                'can_download_tickets' => in_array($order->status, ['completed', 'paid', 'confirmed']) || $order->payment_status === 'paid',
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * List orders for this marketplace client
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Order::where('marketplace_client_id', $client->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders);
    }

    /**
     * Cancel an order (if still pending)
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if ($order->status !== 'pending') {
            return $this->error('Only pending orders can be cancelled', 400);
        }

        try {
            DB::beginTransaction();

            // Restore ticket availability (decrement quota_sold; available_quantity is computed)
            foreach ($order->items as $item) {
                TicketType::where('id', $item->ticket_type_id)
                    ->decrement('quota_sold', $item->quantity);
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Update ticket statuses
            $order->tickets()->update(['status' => 'cancelled']);

            DB::commit();

            Log::info('Marketplace order cancelled', [
                'order_id' => $order->id,
                'marketplace_client_id' => $client->id,
            ]);

            // Send webhook notification (async)
            dispatch(function () use ($client, $order) {
                app(MarketplaceWebhookService::class)->orderCancelled($client, [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => 'cancelled',
                ]);
            })->afterResponse();

            return $this->success(null, 'Order cancelled successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to cancel order', 500);
        }
    }

    /**
     * Refund a completed order
     */
    public function refund(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
            'refund_type' => 'nullable|in:full,partial',
        ]);

        $order = Order::with('marketplaceOrganizer')
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if ($order->status !== 'completed') {
            return $this->error('Only completed orders can be refunded', 400);
        }

        if ($order->refunded_at) {
            return $this->error('Order has already been refunded', 400);
        }

        $refundType = $request->input('refund_type', 'full');
        $refundAmount = $refundType === 'full'
            ? (float) $order->total
            : (float) $request->input('amount', $order->total);

        if ($refundAmount > (float) $order->total) {
            return $this->error('Refund amount cannot exceed order total', 400);
        }

        try {
            DB::beginTransaction();

            // Calculate commission refund proportionally
            $refundRatio = $refundAmount / (float) $order->total;
            $commissionRefund = round((float) $order->commission_amount * $refundRatio, 2);
            $netRefund = $refundAmount - $commissionRefund;

            // Update order
            $order->update([
                'status' => $refundType === 'full' ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
                'refund_amount' => $refundAmount,
                'refund_reason' => $request->reason,
            ]);

            // Invalidate tickets if full refund
            if ($refundType === 'full') {
                $order->tickets()->update(['status' => 'refunded']);

                // Restore ticket availability (decrement quota_sold; available_quantity is computed)
                foreach ($order->items as $item) {
                    TicketType::where('id', $item->ticket_type_id)
                        ->decrement('quota_sold', $item->quantity);
                }
            }

            // Record transaction to deduct from organizer balance
            if ($order->marketplace_organizer_id && $order->marketplaceOrganizer) {
                MarketplaceTransaction::recordRefund(
                    $order->marketplace_client_id,
                    $order->marketplace_organizer_id,
                    $netRefund,
                    $commissionRefund,
                    $order->id,
                    $order->currency
                );

                // Update organizer stats
                $order->marketplaceOrganizer->updateStats();
            }

            DB::commit();

            Log::info('Marketplace order refunded', [
                'order_id' => $order->id,
                'marketplace_client_id' => $client->id,
                'refund_amount' => $refundAmount,
                'refund_type' => $refundType,
            ]);

            // Send webhook notification (async)
            dispatch(function () use ($client, $order, $refundAmount, $refundType) {
                app(MarketplaceWebhookService::class)->orderRefunded($client, [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'refund_amount' => $refundAmount,
                    'refund_type' => $refundType,
                    'refund_reason' => $order->refund_reason,
                    'refunded_at' => $order->refunded_at->toIso8601String(),
                ]);
            })->afterResponse();

            // Send refund notification email to customer via marketplace transport
            if ($order->customer_email && $order->marketplaceClient) {
                $refundClient = $order->marketplaceClient;
                $refundOrder = $order;
                dispatch(function () use ($refundClient, $refundOrder) {
                    try {
                        $event = $refundOrder->event ?? $refundOrder->marketplaceEvent;
                        $eventName = 'Eveniment';
                        if ($event) {
                            $title = $event->title ?? $event->name ?? null;
                            $eventName = is_array($title) ? ($title['ro'] ?? $title['en'] ?? reset($title) ?: 'Eveniment') : ($title ?: 'Eveniment');
                        }
                        $marketplaceName = $refundClient->name;
                        $refundAmount = number_format($refundOrder->refund_amount, 2, ',', '.') . ' ' . ($refundOrder->currency ?? 'RON');

                        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f4f4f8;font-family:Arial,Helvetica,sans-serif;">'
                            . '<div style="max-width:600px;margin:0 auto;padding:24px 16px;">'
                            . '<div style="text-align:center;padding:20px 0;"><h1 style="margin:0;font-size:24px;color:#1a1a2e;">' . e($marketplaceName) . '</h1></div>'
                            . '<div style="background:#ffffff;border-radius:12px;padding:24px;margin-bottom:20px;">'
                            . '<p style="margin:0 0 12px;font-size:16px;color:#333;">Salut, <strong>' . e($refundOrder->customer_name ?? 'Client') . '</strong>,</p>'
                            . '<p style="margin:0 0 12px;font-size:15px;color:#555;">Comanda ta <strong>#' . e($refundOrder->order_number) . '</strong> a fost rambursată.</p>'
                            . '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0;" cellpadding="0" cellspacing="0">'
                            . '<tr><td style="padding:6px 0;color:#888;">Eveniment:</td><td style="padding:6px 0;text-align:right;">' . e($eventName) . '</td></tr>'
                            . '<tr><td style="padding:6px 0;color:#888;">Sumă rambursată:</td><td style="padding:6px 0;text-align:right;font-weight:700;">' . $refundAmount . '</td></tr>'
                            . ($refundOrder->refund_reason ? '<tr><td style="padding:6px 0;color:#888;">Motiv:</td><td style="padding:6px 0;text-align:right;">' . e($refundOrder->refund_reason) . '</td></tr>' : '')
                            . '</table>'
                            . '<p style="margin:16px 0 0;font-size:14px;color:#666;">Rambursarea va fi procesată în contul tău în 5-10 zile lucrătoare.</p>'
                            . '</div>'
                            . '<div style="text-align:center;padding:16px 0;font-size:12px;color:#999;"><p style="margin:0;">Acest email a fost trimis de ' . e($marketplaceName) . '</p></div>'
                            . '</div></body></html>';

                        BaseController::sendViaMarketplace($refundClient, $refundOrder->customer_email, $refundOrder->customer_name ?? 'Client', "Rambursare comandă #{$refundOrder->order_number}", $html, [
                            'order_id' => $refundOrder->id,
                            'template_slug' => 'order_refunded',
                        ]);
                    } catch (\Throwable $e) {
                        \Log::channel('marketplace')->error('Failed to send refund email', [
                            'order_id' => $refundOrder->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                })->afterResponse();
            }

            return $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'refund_amount' => $refundAmount,
                'refund_type' => $refundType,
            ], 'Order refunded successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to refund marketplace order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to refund order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send tickets to an email address (POS flow)
     */
    public function sendTickets(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $request->validate([
            'email' => 'required|email',
        ]);

        $order = Order::with(['tickets.ticketType', 'event'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        $email = $request->input('email');

        // Update customer email on order
        $order->update(['customer_email' => $email]);

        // Update customer record if exists
        if ($order->customer_id) {
            Customer::where('id', $order->customer_id)->update(['email' => $email]);
        }

        // Send ticket email
        try {
            $this->sendPosTicketEmail($order, $email, $client);
        } catch (\Throwable $e) {
            Log::error('Failed to send POS ticket email', [
                'order_id' => $order->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the request - email updated successfully even if send fails
        }

        return $this->success(null, 'Tickets sent to ' . $email);
    }

    /**
     * Complete a POS order with optional auto check-in
     */
    public function posComplete(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::with('tickets.ticketType')
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        $autoCheckin = $request->boolean('auto_checkin', false);
        $checkedIn = [];

        if ($autoCheckin) {
            $checkinBy = $request->input('checked_in_by', 'POS');
            $now = now();

            foreach ($order->tickets as $ticket) {
                if ($ticket->status === 'valid' && !$ticket->checked_in_at) {
                    $ticket->update([
                        'checked_in_at' => $now,
                        'checked_in_by' => $checkinBy,
                    ]);
                    $checkedIn[] = [
                        'id' => $ticket->id,
                        'barcode' => $ticket->barcode,
                        'code' => $ticket->code,
                        'ticket_type' => $ticket->ticketType?->name,
                        'checked_in_at' => $now->toIso8601String(),
                    ];
                }
            }
        }

        return $this->success([
            'order_id' => $order->id,
            'checked_in' => $checkedIn,
            'checked_in_count' => count($checkedIn),
        ], $autoCheckin ? 'Tickets checked in' : 'Order completed');
    }

    /**
     * Send a simple ticket email for POS orders via marketplace transport
     */
    private function sendPosTicketEmail(Order $order, string $email, $client): void
    {
        $order->load(['tickets.ticketType', 'event']);
        $event = $order->event;
        $rawTitle = $event?->title;
        $eventName = is_array($rawTitle)
            ? ($rawTitle['ro'] ?? $rawTitle['en'] ?? reset($rawTitle) ?: 'Eveniment')
            : ($rawTitle ?? 'Eveniment');

        // Build ticket list HTML
        $ticketRows = '';
        foreach ($order->tickets as $ticket) {
            $typeName = $ticket->ticketType?->name ?? 'Bilet';
            $code = $ticket->code;
            $series = $ticket->meta['ticket_series'] ?? '';
            $seriesCell = $series ? "<br><span style='font-size:11px;color:#666;'>Serie: {$series}</span>" : '';
            $ticketRows .= "<tr><td style='padding:8px;border-bottom:1px solid #eee;'>{$typeName}</td><td style='padding:8px;border-bottom:1px solid #eee;font-family:monospace;'>{$code}{$seriesCell}</td></tr>";
        }

        $totalFormatted = number_format($order->total, 2, ',', '.') . ' ' . ($order->currency ?? 'RON');
        $marketplaceName = $client->name ?? 'AmBilet';

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#333;'>Biletele tale pentru " . e($eventName) . "</h2>
            <p>Comanda: <strong>{$order->order_number}</strong></p>
            <p>Total: <strong>{$totalFormatted}</strong></p>
            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='background:#f5f5f5;'>
                    <th style='padding:10px;text-align:left;'>Tip bilet</th>
                    <th style='padding:10px;text-align:left;'>Cod</th>
                </tr>
                {$ticketRows}
            </table>
            <p style='color:#666;font-size:13px;'>Prezintă acest email sau codurile la intrare.</p>
            <p style='color:#999;font-size:12px;margin-top:30px;'>Trimis de " . e($marketplaceName) . "</p>
        </div>";

        $this->sendMarketplaceEmail($client, $email, $order->customer_name ?? 'Client', "Biletele tale - {$eventName}", $html, [
            'order_id' => $order->id,
            'template_slug' => 'pos_tickets',
        ]);
    }

    /**
     * Get sales breakdown for an event (online vs POS, by user)
     */
    public function salesBreakdown(Request $request, int $eventId): JsonResponse
    {
        $client = $this->requireClient($request);

        $orders = Order::with('items')
            ->where('event_id', $eventId)
            ->where('marketplace_client_id', $client->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();

        $online = $orders->where('source', '!=', 'pos_app');
        $pos = $orders->where('source', 'pos_app');

        // Group POS by sold_by from meta
        $posByUser = $pos->groupBy(function ($o) {
            $meta = is_array($o->meta) ? $o->meta : (is_string($o->meta) ? json_decode($o->meta, true) : []);
            return $meta['sold_by'] ?? 'POS';
        });

        return $this->success([
            'online' => [
                'orders' => $online->count(),
                'tickets' => $online->sum(fn($o) => $o->items->sum('quantity')),
                'revenue' => round($online->sum('total'), 2),
            ],
            'pos' => [
                'orders' => $pos->count(),
                'tickets' => $pos->sum(fn($o) => $o->items->sum('quantity')),
                'revenue' => round($pos->sum('total'), 2),
                'by_user' => $posByUser->map(fn($userOrders, $userName) => [
                    'user' => $userName,
                    'orders' => $userOrders->count(),
                    'tickets' => $userOrders->sum(fn($o) => $o->items->sum('quantity')),
                    'revenue' => round($userOrders->sum('total'), 2),
                ])->values(),
            ],
        ]);
    }

    /**
     * Generate a QR claim URL for a POS cash order
     * Customer scans QR to enter their details and receive tickets by email
     */
    public function generateClaimUrl(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::with(['event.venue', 'tickets'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        // Resolve event name from translatable title
        $event = $order->event;
        $rawTitle = $event?->title;
        $eventName = is_array($rawTitle)
            ? ($rawTitle['ro'] ?? $rawTitle['en'] ?? reset($rawTitle) ?: 'Eveniment')
            : ($rawTitle ?? 'Eveniment');

        // Resolve event date
        $eventDate = $event?->start_date
            ? (is_string($event->start_date) ? $event->start_date : $event->start_date->format('d.m.Y'))
            : null;

        // Resolve venue name
        $rawVenueName = $event?->venue?->name;
        $venueName = is_array($rawVenueName)
            ? ($rawVenueName['ro'] ?? $rawVenueName['en'] ?? reset($rawVenueName) ?: null)
            : $rawVenueName;

        try {
            $claim = PosTicketClaim::create([
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'venue_name' => $venueName,
            ]);

            return $this->success([
                'claim_url' => $claim->getClaimUrl(),
                'token' => $claim->token,
                'expires_at' => $claim->expires_at->toIso8601String(),
            ], 'Claim URL generated');
        } catch (\Throwable $e) {
            Log::error('Failed to generate claim URL', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to generate claim URL: ' . $e->getMessage(), 500);
        }
    }
}
