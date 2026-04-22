<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\EventGoal;
use App\Models\EventMilestone;
use App\Models\MarketplaceOrganizer;
use App\Models\TicketType;
use App\Models\MarketplaceTransaction;
use App\Models\Order;
use App\Models\VenueOwnerNote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventsController extends BaseController
{
    /**
     * List organizer's events
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->with(['ticketTypes', 'venue', 'marketplaceCity']);

        // Filters - map 'status' to Event fields
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'draft') {
                $query->where('is_published', false);
            } elseif ($status === 'published' || $status === 'approved') {
                $query->where('is_published', true);
            } elseif ($status === 'cancelled') {
                $query->where('is_cancelled', true);
            }
        }

        if ($request->has('upcoming')) {
            $query->upcoming();
        }

        if ($request->has('past')) {
            $query->past();
        }

        // Sorting - map starts_at to event_date
        $sortField = $request->get('sort', 'event_date');
        if ($sortField === 'starts_at') {
            $sortField = 'event_date';
        }
        $sortDir = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events, function ($event) {
            return $this->formatEvent($event);
        });
    }

    /**
     * Get single event
     */
    public function show(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::with(['ticketTypes', 'venue', 'marketplaceCity', 'marketplaceEventCategory', 'eventGenres', 'artists'])
            ->where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        return $this->success([
            'event' => $this->formatEventDetailed($event),
        ]);
    }

    /**
     * Create a new event
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        if (!$organizer->isActive()) {
            return $this->error('Your account must be approved before creating events', 403);
        }

        $isDraft = $request->boolean('is_draft', false);

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'ticket_terms' => 'nullable|string|max:10000',
            'short_description' => 'nullable|string|max:500',
            'duration_mode' => 'nullable|string|in:single_day,range,multi_day',
            'starts_at' => $isDraft ? 'nullable|date' : 'required|date|after:now',
            'ends_at' => 'nullable|date',
            'doors_open_at' => 'nullable|date',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'venue_name' => $isDraft ? 'nullable|string|max:255' : 'required|string|max:255',
            'venue_address' => 'nullable|string|max:500',
            'venue_city' => $isDraft ? 'nullable|string|max:100' : 'required|string|max:100',
            'marketplace_event_category_id' => 'nullable|integer|exists:marketplace_event_categories,id',
            'genre_ids' => 'nullable|array',
            'artist_ids' => 'nullable|array',
            'website_url' => 'nullable|url|max:500',
            'facebook_url' => 'nullable|url|max:500',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'capacity' => 'nullable|integer|min:1',
            'max_tickets_per_order' => 'nullable|integer|min:1|max:50',
            'sales_start_at' => 'nullable|date',
            'sales_end_at' => 'nullable|date',
            'ticket_types' => $isDraft ? 'nullable|array' : 'required|array|min:1',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.description' => 'nullable|string|max:500',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity' => 'nullable|integer|min:1',
            'ticket_types.*.min_per_order' => 'nullable|integer|min:1',
            'ticket_types.*.max_per_order' => 'nullable|integer|min:1',
        ];

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Event::where('marketplace_client_id', $organizer->marketplace_client_id)
                ->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Parse dates for Event model
            $startsAt = $validated['starts_at'] ?? null;
            $endsAt = $validated['ends_at'] ?? null;
            $doorsAt = $validated['doors_open_at'] ?? null;
            $durationMode = $validated['duration_mode'] ?? 'single_day';

            $startDate = $startsAt ? Carbon::parse($startsAt)->toDateString() : null;
            $startTime = $startsAt ? Carbon::parse($startsAt)->format('H:i') : null;
            $endDate = $endsAt ? Carbon::parse($endsAt)->toDateString() : null;
            $endTime = $endsAt ? Carbon::parse($endsAt)->format('H:i') : null;
            $doorTime = $doorsAt ? Carbon::parse($doorsAt)->format('H:i') : null;

            // Build date fields based on duration mode
            $dateFields = [];
            if ($durationMode === 'range') {
                $dateFields = [
                    'range_start_date' => $startDate,
                    'range_start_time' => $startTime,
                    'range_end_date' => $endDate,
                    'range_end_time' => $endTime,
                ];
            } else {
                $dateFields = [
                    'event_date' => $startDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'door_time' => $doorTime,
                ];
            }

            $event = Event::create(array_merge([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'title' => ['ro' => $validated['name'], 'en' => $validated['name']],
                'slug' => $slug,
                'description' => ['ro' => $validated['description'] ?? '', 'en' => $validated['description'] ?? ''],
                'ticket_terms' => ['ro' => $validated['ticket_terms'] ?? '', 'en' => $validated['ticket_terms'] ?? ''],
                'short_description' => ['ro' => $validated['short_description'] ?? '', 'en' => $validated['short_description'] ?? ''],
                'duration_mode' => $durationMode,
                'venue_id' => $validated['venue_id'] ?? null,
                'suggested_venue_name' => empty($validated['venue_id']) ? ($validated['venue_name'] ?? null) : null,
                'address' => $validated['venue_address'] ?? null,
                'marketplace_event_category_id' => $validated['marketplace_event_category_id'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'facebook_url' => $validated['facebook_url'] ?? null,
                'is_published' => false,
            ], $dateFields));

            // Create ticket types using TicketType model
            foreach ($validated['ticket_types'] ?? [] as $index => $ticketTypeData) {
                TicketType::create([
                    'event_id' => $event->id,
                    'name' => $ticketTypeData['name'],
                    'description' => $ticketTypeData['description'] ?? null,
                    'price_cents' => (int) ($ticketTypeData['price'] * 100),
                    'currency' => 'RON',
                    'quota_total' => isset($ticketTypeData['quantity']) ? (int) $ticketTypeData['quantity'] : -1,
                    'quota_sold' => 0,
                    'status' => 'active',
                ]);
            }

            // Sync genres if provided
            if (!empty($validated['genre_ids'])) {
                $event->eventGenres()->sync($validated['genre_ids']);
            }

            // Sync artists if provided
            if (!empty($validated['artist_ids'])) {
                $event->artists()->sync($validated['artist_ids']);
            }

            DB::commit();

            return $this->success([
                'event' => $this->formatEventDetailed($event->load(['ticketTypes', 'eventGenres', 'artists', 'venue'])),
            ], 'Event created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an event
     */
    public function update(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Prevent editing of published events completely
        if ($event->is_published) {
            return $this->error('Nu poți modifica un eveniment care este deja publicat și live. Contactează suportul pentru modificări.', 403);
        }

        $rules = [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:10000',
            'ticket_terms' => 'nullable|string|max:10000',
            'short_description' => 'nullable|string|max:500',
            'duration_mode' => 'nullable|string|in:single_day,range,multi_day',
            'doors_open_at' => 'nullable|date',
            'marketplace_event_category_id' => 'nullable|integer|exists:marketplace_event_categories,id',
            'genre_ids' => 'nullable|array',
            'artist_ids' => 'nullable|array',
            'website_url' => 'nullable|url|max:500',
            'facebook_url' => 'nullable|url|max:500',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
        ];

        // Draft events can edit all fields
        $isDraft = $request->boolean('is_draft', false);
        $rules = array_merge($rules, [
            'starts_at' => $isDraft ? 'nullable|date' : 'sometimes|date|after:now',
            'ends_at' => 'nullable|date',
            'venue_id' => 'nullable|integer|exists:venues,id',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string|max:500',
            'venue_city' => 'nullable|string|max:100',
            'capacity' => 'nullable|integer|min:1',
            'max_tickets_per_order' => 'nullable|integer|min:1|max:50',
            'sales_start_at' => 'nullable|date',
            'sales_end_at' => 'nullable|date',
            'ticket_types' => 'nullable|array',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.description' => 'nullable|string|max:500',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity' => 'nullable|integer|min:1',
            'ticket_types.*.min_per_order' => 'nullable|integer|min:1',
            'ticket_types.*.max_per_order' => 'nullable|integer|min:1',
        ]);

        $validated = $request->validate($rules);

        try {
            DB::beginTransaction();

            // Remove ticket_types from validated data before updating event
            $ticketTypesData = $validated['ticket_types'] ?? null;
            unset($validated['ticket_types']);

            // Map API fields to Event model fields
            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['title'] = ['ro' => $validated['name'], 'en' => $validated['name']];
            }
            if (array_key_exists('description', $validated)) {
                $desc = $validated['description'] ?? '';
                $updateData['description'] = ['ro' => $desc, 'en' => $desc];
            }
            if (array_key_exists('ticket_terms', $validated)) {
                $terms = $validated['ticket_terms'] ?? '';
                $updateData['ticket_terms'] = ['ro' => $terms, 'en' => $terms];
            }
            if (array_key_exists('short_description', $validated)) {
                $short = $validated['short_description'] ?? '';
                $updateData['short_description'] = ['ro' => $short, 'en' => $short];
            }
            // Duration mode
            $durationMode = $validated['duration_mode'] ?? $event->duration_mode ?? 'single_day';
            if (isset($validated['duration_mode'])) {
                $updateData['duration_mode'] = $durationMode;
            }

            // Map dates based on duration mode
            if (isset($validated['starts_at']) || isset($validated['ends_at'])) {
                if ($durationMode === 'range') {
                    // Clear single_day fields
                    $updateData['event_date'] = null;
                    $updateData['start_time'] = null;
                    $updateData['end_time'] = null;
                    $updateData['door_time'] = null;

                    if (isset($validated['starts_at'])) {
                        $startsAt = Carbon::parse($validated['starts_at']);
                        $updateData['range_start_date'] = $startsAt->toDateString();
                        $updateData['range_start_time'] = $startsAt->format('H:i');
                    }
                    if (isset($validated['ends_at'])) {
                        $endsAt = Carbon::parse($validated['ends_at']);
                        $updateData['range_end_date'] = $endsAt->toDateString();
                        $updateData['range_end_time'] = $endsAt->format('H:i');
                    }
                } else {
                    // Clear range fields
                    $updateData['range_start_date'] = null;
                    $updateData['range_end_date'] = null;
                    $updateData['range_start_time'] = null;
                    $updateData['range_end_time'] = null;

                    if (isset($validated['starts_at'])) {
                        $startsAt = Carbon::parse($validated['starts_at']);
                        $updateData['event_date'] = $startsAt->toDateString();
                        $updateData['start_time'] = $startsAt->format('H:i');
                    }
                    if (isset($validated['ends_at'])) {
                        $endsAt = Carbon::parse($validated['ends_at']);
                        $updateData['end_time'] = $endsAt->format('H:i');
                    }
                }
            }
            if (isset($validated['doors_open_at'])) {
                $doorsAt = Carbon::parse($validated['doors_open_at']);
                $updateData['door_time'] = $doorsAt->format('H:i');
            }
            if (isset($validated['venue_id'])) {
                $updateData['venue_id'] = $validated['venue_id'];
                $updateData['suggested_venue_name'] = null; // Clear suggestion when venue is selected
            } elseif (isset($validated['venue_name'])) {
                $updateData['suggested_venue_name'] = $validated['venue_name'];
            }
            if (isset($validated['venue_address'])) {
                $updateData['address'] = $validated['venue_address'];
            }
            if (isset($validated['marketplace_event_category_id'])) {
                $updateData['marketplace_event_category_id'] = $validated['marketplace_event_category_id'];
            }
            if (isset($validated['website_url'])) {
                $updateData['website_url'] = $validated['website_url'];
            }
            if (isset($validated['facebook_url'])) {
                $updateData['facebook_url'] = $validated['facebook_url'];
            }

            if (!empty($updateData)) {
                $event->update($updateData);
            }

            // Sync ticket types if provided (only for unpublished events - we block published above)
            if ($ticketTypesData !== null) {
                // Delete existing ticket types and recreate
                $event->ticketTypes()->delete();

                foreach ($ticketTypesData as $index => $ticketTypeData) {
                    TicketType::create([
                        'event_id' => $event->id,
                        'name' => $ticketTypeData['name'],
                        'description' => $ticketTypeData['description'] ?? null,
                        'price_cents' => (int) ($ticketTypeData['price'] * 100),
                        'currency' => 'RON',
                        'quota_total' => isset($ticketTypeData['quantity']) ? (int) $ticketTypeData['quantity'] : -1,
                        'quota_sold' => 0,
                        'status' => 'active',
                    ]);
                }
            }

            // Sync genres if provided
            if (isset($validated['genre_ids'])) {
                $event->eventGenres()->sync($validated['genre_ids']);
            }

            // Sync artists if provided
            if (isset($validated['artist_ids'])) {
                $event->artists()->sync($validated['artist_ids']);
            }

            DB::commit();

            return $this->success([
                'event' => $this->formatEventDetailed($event->fresh()->load(['ticketTypes', 'eventGenres', 'artists', 'venue'])),
            ], 'Event updated');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit event for review (publish event)
     */
    public function submit(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->is_published) {
            return $this->error('Evenimentul este deja publicat', 400);
        }

        if ($event->submitted_at) {
            return $this->error('Evenimentul a fost deja trimis spre aprobare', 400);
        }

        // Validate event has required data
        if (!$event->ticketTypes()->exists()) {
            return $this->error('Event must have at least one ticket type', 400);
        }

        $event->update(['submitted_at' => now()]);

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load(['ticketTypes', 'venue'])),
        ], 'Evenimentul a fost trimis spre aprobare');
    }

    /**
     * Cancel an event with automatic refunds
     */
    public function cancel(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->is_cancelled) {
            return $this->error('Event is already cancelled', 400);
        }

        $reason = $validated['reason'] ?? 'Event cancelled by organizer';

        try {
            DB::beginTransaction();

            // Get all paid/completed orders for this event (to refund)
            $completedOrders = $event->orders()->whereIn('status', ['paid', 'confirmed', 'completed'])->get();
            $refundedCount = 0;
            $totalRefunded = 0;

            foreach ($completedOrders as $order) {
                // Calculate refund
                $refundAmount = (float) $order->total;
                $commissionRefund = (float) $order->commission_amount;
                $netRefund = $refundAmount - $commissionRefund;

                // Update order status
                $order->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $reason,
                ]);

                // Invalidate tickets
                $order->tickets()->update(['status' => 'refunded']);

                // Record refund transaction
                if ($order->marketplace_organizer_id) {
                    MarketplaceTransaction::recordRefund(
                        $order->marketplace_client_id,
                        $order->marketplace_organizer_id,
                        $netRefund,
                        $commissionRefund,
                        $order->id,
                        $order->currency
                    );
                }

                // Send email notification to customer via marketplace transport
                if ($order->customer_email && $order->marketplace_client_id) {
                    $cancelClient = \App\Models\MarketplaceClient::find($order->marketplace_client_id);
                    if ($cancelClient) {
                        $cancelOrder = $order;
                        $cancelEventName = 'Eveniment';
                        $rawCancelTitle = $event->title ?? $event->name ?? null;
                        if ($rawCancelTitle) {
                            $cancelEventName = is_array($rawCancelTitle) ? ($rawCancelTitle['ro'] ?? $rawCancelTitle['en'] ?? reset($rawCancelTitle) ?: 'Eveniment') : ($rawCancelTitle ?: 'Eveniment');
                        }
                        $cancelMarketplaceName = $cancelClient->name;
                        $cancelTotal = number_format($cancelOrder->total, 2, ',', '.') . ' ' . ($cancelOrder->currency ?? 'RON');

                        dispatch(function () use ($cancelClient, $cancelOrder, $cancelEventName, $cancelMarketplaceName, $cancelTotal) {
                            try {
                                $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f4f4f8;font-family:Arial,Helvetica,sans-serif;">'
                                    . '<div style="max-width:600px;margin:0 auto;padding:24px 16px;">'
                                    . '<div style="text-align:center;padding:20px 0;"><h1 style="margin:0;font-size:24px;color:#1a1a2e;">' . e($cancelMarketplaceName) . '</h1></div>'
                                    . '<div style="background:#ffffff;border-radius:12px;padding:24px;margin-bottom:20px;">'
                                    . '<p style="margin:0 0 12px;font-size:16px;color:#333;">Salut, <strong>' . e($cancelOrder->customer_name ?? 'Client') . '</strong>,</p>'
                                    . '<p style="margin:0 0 12px;font-size:15px;color:#555;">Din păcate, evenimentul <strong>' . e($cancelEventName) . '</strong> a fost anulat.</p>'
                                    . '<p style="margin:0 0 12px;font-size:15px;color:#555;">Comanda ta <strong>#' . e($cancelOrder->order_number) . '</strong> a fost rambursată automat cu suma de <strong>' . $cancelTotal . '</strong>.</p>'
                                    . '<p style="margin:0 0 12px;font-size:14px;color:#666;">Rambursarea va fi procesată în contul tău în 5-10 zile lucrătoare.</p>'
                                    . '<p style="margin:16px 0 0;font-size:14px;color:#666;">Ne cerem scuze pentru neplăcerile create.</p>'
                                    . '</div>'
                                    . '<div style="text-align:center;padding:16px 0;font-size:12px;color:#999;"><p style="margin:0;">Acest email a fost trimis de ' . e($cancelMarketplaceName) . '</p></div>'
                                    . '</div></body></html>';

                                BaseController::sendViaMarketplace($cancelClient, $cancelOrder->customer_email, $cancelOrder->customer_name ?? 'Client', "Eveniment anulat — rambursare automată", $html, [
                                    'order_id' => $cancelOrder->id,
                                    'template_slug' => 'event_cancelled',
                                ]);
                            } catch (\Throwable $e) {
                                \Log::channel('marketplace')->error('Failed to send event cancelled email', [
                                    'order_id' => $cancelOrder->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        })->afterResponse();
                    }
                }

                $refundedCount++;
                $totalRefunded += $refundAmount;
            }

            // Cancel pending orders
            $pendingOrders = $event->orders()->where('status', 'pending')->get();
            foreach ($pendingOrders as $order) {
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                $order->tickets()->update(['status' => 'cancelled']);

                // Restore ticket availability
                foreach ($order->items as $item) {
                    if ($item->ticket_type_id) {
                        $event->ticketTypes()
                            ->where('id', $item->ticket_type_id)
                            ->increment('quota_total', $item->quantity);
                    }
                }
            }

            // Cancel the event
            $event->update([
                'is_cancelled' => true,
                'cancel_reason' => $reason,
            ]);

            // Update organizer stats
            $organizer->updateStats();

            DB::commit();

            return $this->success([
                'orders_refunded' => $refundedCount,
                'total_refunded' => $totalRefunded,
                'orders_cancelled' => $pendingOrders->count(),
            ], 'Event cancelled. ' . $refundedCount . ' orders have been refunded.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to cancel event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update event status (sold out, door sales, postponed)
     */
    public function updateStatus(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'is_sold_out' => 'sometimes|boolean',
            'door_sales_only' => 'sometimes|boolean',
            'is_postponed' => 'sometimes|boolean',
            'postponed_date' => 'nullable|date',
            'postponed_start_time' => 'nullable|string|max:10',
            'postponed_door_time' => 'nullable|string|max:10',
            'postponed_end_time' => 'nullable|string|max:10',
            'postponed_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $updateData = [];

            if (array_key_exists('is_sold_out', $validated)) {
                $updateData['is_sold_out'] = $validated['is_sold_out'];
            }

            if (array_key_exists('door_sales_only', $validated)) {
                $updateData['door_sales_only'] = $validated['door_sales_only'];
            }

            if (array_key_exists('is_postponed', $validated)) {
                $updateData['is_postponed'] = $validated['is_postponed'];
                if ($validated['is_postponed']) {
                    // Set postponed data
                    $updateData['postponed_date'] = $validated['postponed_date'] ?? null;
                    $updateData['postponed_start_time'] = $validated['postponed_start_time'] ?? null;
                    $updateData['postponed_door_time'] = $validated['postponed_door_time'] ?? null;
                    $updateData['postponed_end_time'] = $validated['postponed_end_time'] ?? null;
                    $updateData['postponed_reason'] = $validated['postponed_reason'] ?? null;
                } else {
                    // Clear postponed data
                    $updateData['postponed_date'] = null;
                    $updateData['postponed_start_time'] = null;
                    $updateData['postponed_door_time'] = null;
                    $updateData['postponed_end_time'] = null;
                    $updateData['postponed_reason'] = null;
                }
            }

            if (!empty($updateData)) {
                $event->update($updateData);
            }

            return $this->success([
                'event' => $this->formatEventDetailed($event->fresh()->load(['ticketTypes', 'eventGenres', 'artists', 'venue'])),
            ], 'Event status updated');

        } catch (\Exception $e) {
            return $this->error('Failed to update event status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload images for an event (poster and/or cover)
     */
    public function uploadImages(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $request->validate([
            'poster' => 'nullable|image|mimes:jpeg,png,webp|max:10240',
            'cover_image' => 'nullable|image|mimes:jpeg,png,webp|max:10240',
        ]);

        $updateData = [];

        if ($request->hasFile('poster')) {
            $posterPath = $request->file('poster')->store('events/posters', 'public');
            $updateData['poster_url'] = $posterPath;
            $updateData['poster_original_filename'] = $request->file('poster')->getClientOriginalName();
        }

        if ($request->hasFile('cover_image')) {
            $heroPath = $request->file('cover_image')->store('events/hero', 'public');
            $updateData['hero_image_url'] = $heroPath;
            $updateData['hero_image_original_filename'] = $request->file('cover_image')->getClientOriginalName();
        }

        if (empty($updateData)) {
            return $this->error('No images provided', 400);
        }

        $event->update($updateData);

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load(['ticketTypes', 'venue'])),
        ], 'Images uploaded successfully');
    }

    /**
     * Delete an event (only unpublished events without orders)
     */
    public function destroy(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Only allow deletion of unpublished events
        if ($event->is_published) {
            return $this->error('Only unpublished events can be deleted', 400);
        }

        // Check for any orders
        if ($event->orders()->exists()) {
            return $this->error('Cannot delete event with existing orders', 400);
        }

        try {
            DB::beginTransaction();

            // Delete ticket types
            $event->ticketTypes()->delete();

            // Delete the event
            $event->delete();

            // Update organizer stats
            $organizer->updateStats();

            DB::commit();

            return $this->success(null, 'Event deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get event participants (ticket holders)
     */
    public function participants(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Count tickets from paid/confirmed/completed orders (confirmed = POS cash)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        $query = \App\Models\Ticket::whereHas('order', function ($q) use ($event, $validOrderStatuses, $organizer) {
                $q->where('event_id', $event->id)
                    ->whereIn('status', $validOrderStatuses)
                    ->where('marketplace_organizer_id', $organizer->id);
            })
            ->with(['order.marketplaceCustomer', 'ticketType']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('checked_in')) {
            if ($request->checked_in === 'true' || $request->checked_in === '1') {
                $query->whereNotNull('checked_in_at');
            } else {
                $query->whereNull('checked_in_at');
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('customer_email', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('order.marketplaceCustomer', function ($cq) use ($search) {
                        $cq->where('email', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('ticket_type_id')) {
            $query->where('ticket_type_id', $request->ticket_type_id);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 50), 200);
        $tickets = $query->paginate($perPage);

        // Get stats — count only valid/used tickets (excludes cancelled/refunded)
        $totalTickets = \App\Models\Ticket::whereHas('order', function ($q) use ($event, $validOrderStatuses, $organizer) {
            $q->where('event_id', $event->id)->whereIn('status', $validOrderStatuses)
                ->where('marketplace_organizer_id', $organizer->id);
        })->whereIn('status', ['valid', 'used'])->count();

        $checkedInCount = \App\Models\Ticket::whereHas('order', function ($q) use ($event, $validOrderStatuses, $organizer) {
            $q->where('event_id', $event->id)->whereIn('status', $validOrderStatuses)
                ->where('marketplace_organizer_id', $organizer->id);
        })->whereNotNull('checked_in_at')->count();

        return $this->paginated($tickets, function ($ticket) {
            $customer = $ticket->order->marketplaceCustomer;
            $ticketType = $ticket->ticketType;
            $ticketMeta = is_array($ticket->meta) ? $ticket->meta : [];
            $attendeePhone = $ticketMeta['attendee_phone'] ?? $ticketMeta['beneficiary_phone'] ?? null;

            return [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'code' => $ticket->code,
                'qr_code' => $ticket->qr_code_url ?? null,
                'ticket_type' => $ticketType?->name,
                'ticket_type_id' => $ticket->ticket_type_id,
                'price' => (float) ($ticketType?->display_price ?? 0),
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'checked_in_by' => $ticket->checked_in_by,
                'customer' => [
                    'name' => $customer
                        ? $customer->first_name . ' ' . $customer->last_name
                        : $ticket->order->customer_name,
                    'email' => $customer?->email ?? $ticket->order->customer_email,
                    'phone' => $customer?->phone ?? $ticket->order->customer_phone,
                ],
                'attendee' => [
                    'name' => $ticket->attendee_name,
                    'email' => $ticket->attendee_email,
                    'phone' => $attendeePhone,
                ],
                'order_number' => $ticket->order->order_number,
                'source' => $ticket->order->source,
                'is_invitation' => !empty($ticket->order->meta['is_invitation']),
                'purchased_at' => $ticket->created_at->toIso8601String(),
            ];
        }, [
            'stats' => [
                'total' => $totalTickets,
                'checked_in' => $checkedInCount,
                'not_checked_in' => $totalTickets - $checkedInCount,
                'check_in_rate' => $totalTickets > 0 ? round(($checkedInCount / $totalTickets) * 100, 1) : 0,
            ],
        ]);
    }

    /**
     * Get all participants across all organizer's events
     */
    public function allParticipants(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Get all events for this organizer
        $eventIds = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->pluck('id');

        // Count tickets from paid/confirmed/completed orders (confirmed = POS cash)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        $query = \App\Models\Ticket::whereHas('order', function ($q) use ($eventIds, $validOrderStatuses) {
                $q->whereIn('event_id', $eventIds)
                    ->whereIn('status', $validOrderStatuses);
            })
            ->with(['order.marketplaceCustomer', 'order.event', 'ticketType']);

        // Filters
        if ($request->has('event_id')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('event_id', $request->event_id);
            });
        }

        if ($request->has('checked_in')) {
            if ($request->checked_in === 'checked_in' || $request->checked_in === 'true' || $request->checked_in === '1') {
                $query->whereNotNull('checked_in_at');
            } elseif ($request->checked_in === 'not_checked' || $request->checked_in === 'false' || $request->checked_in === '0') {
                $query->whereNull('checked_in_at');
            }
        }

        // Search functionality disabled for privacy

        $query->orderBy('created_at', 'desc');

        // Get stats - per event if event_id is provided, otherwise all events
        $statsEventIds = $request->has('event_id') ? [$request->event_id] : $eventIds->toArray();

        $statsQuery = \App\Models\Ticket::whereHas('order', function ($q) use ($statsEventIds, $validOrderStatuses, $organizer) {
            $q->whereIn('event_id', $statsEventIds)
                ->whereIn('status', $validOrderStatuses)
                ->where('marketplace_organizer_id', $organizer->id);
        })->whereIn('status', ['valid', 'used']);

        $totalTickets = $statsQuery->count();
        $checkedInCount = (clone $statsQuery)->whereNotNull('checked_in_at')->count();

        // Calculate revenue from valid/used tickets only
        $revenue = (float) \App\Models\Ticket::whereIn('event_id', $statsEventIds)
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', function ($q) use ($validOrderStatuses, $organizer) {
                $q->whereIn('status', $validOrderStatuses)
                    ->where('marketplace_organizer_id', $organizer->id);
            })
            ->sum('price');

        // Get unique orders count
        $ordersCount = Order::whereIn('event_id', $statsEventIds)
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', $validOrderStatuses)
            ->count();

        $tickets = $query->take(200)->get();

        $participants = $tickets->map(function ($ticket) {
            $customer = $ticket->order->marketplaceCustomer;
            $event = $ticket->order->event;

            $rawName = $customer
                ? $customer->first_name . ' ' . $customer->last_name
                : $ticket->order->customer_name ?? 'Unknown';
            $rawEmail = $customer?->email ?? $ticket->order->customer_email ?? '';

            // Get localized event title
            $eventTitle = 'Unknown Event';
            if ($event) {
                $eventTitle = $event->getTranslation('title', 'ro')
                    ?: $event->getTranslation('title', 'en')
                    ?: $event->getTranslation('title')
                    ?: 'Unknown Event';
            }

            $phone = $customer?->phone ?? $ticket->order->customer_phone ?? '';

            return [
                'id' => $ticket->id,
                'ticket_id' => $ticket->id,
                'name' => $rawName,
                'email' => $this->maskEmail($rawEmail),
                'phone' => $phone,
                'event' => $eventTitle,
                'event_id' => $event?->id,
                'ticket_type' => $ticket->ticketType?->name ?? 'Standard',
                'ticket_code' => $ticket->barcode,
                'control_code' => $ticket->code,
                'seat_label' => $ticket->seat_label ?? null,
                'checked_in' => $ticket->checked_in_at !== null,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'order_id' => $ticket->order->id,
                'order_number' => $ticket->order->order_number,
                'order_date' => $ticket->order->created_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'participants' => $participants,
            'stats' => [
                'total' => $totalTickets,
                'checked_in' => $checkedInCount,
                'pending' => $totalTickets - $checkedInCount,
                'rate' => $totalTickets > 0 ? round(($checkedInCount / $totalTickets) * 100, 1) : 0,
                'revenue' => (float) $revenue,
                'orders_count' => $ordersCount,
            ],
        ]);
    }

    /**
     * Check in a ticket by barcode (across all organizer's events)
     */
    public function checkInByCode(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $request->validate([
            'ticket_code' => 'required|string',
        ]);

        $barcode = $request->ticket_code;

        // Get all events for this organizer
        $eventIds = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->pluck('id');

        // Count tickets from paid/confirmed/completed orders (confirmed = POS cash)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        // Search by barcode (full code) or code (control code)
        $ticket = \App\Models\Ticket::where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)
                    ->orWhere('code', $barcode);
            })
            ->whereHas('order', function ($q) use ($eventIds, $validOrderStatuses) {
                $q->whereIn('event_id', $eventIds)
                    ->whereIn('status', $validOrderStatuses);
            })
            ->first();

        // Fallback: search external tickets if not found in main tickets table
        if (!$ticket) {
            return $this->checkInExternalTicket($barcode, $eventIds->toArray(), $organizer);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        // Venue-owner private notes (visible to marketplace scanners per product requirement)
        $venueNotes = $this->resolveVenueNotesForTicket($ticket);

        if ($ticket->checked_in_at) {
            $dupCustomer = $ticket->order->marketplaceCustomer;
            $dupSeatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
            return response()->json([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
                'ticket' => [
                    'barcode' => $ticket->barcode,
                    'ticket_type' => $ticket->ticketType?->name,
                    'checked_in_at' => $ticket->checked_in_at->toIso8601String(),
                    'checked_in_by' => $ticket->checked_in_by,
                    'section' => $dupSeatDetails['section_name'] ?? null,
                    'row' => $dupSeatDetails['row_label'] ?? null,
                    'seat' => $dupSeatDetails['seat_number'] ?? null,
                    'attendee_name' => $ticket->attendee_name,
                ],
                'customer' => [
                    'name' => $dupCustomer
                        ? $dupCustomer->first_name . ' ' . $dupCustomer->last_name
                        : $ticket->order->customer_name,
                ],
                'order' => [
                    'source' => $ticket->order->source ?? 'online',
                    'customer_name' => $ticket->order->customer_name,
                ],
                'venue_notes' => $venueNotes,
            ], 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $organizer->contact_name ?? $organizer->name,
        ]);

        $customer = $ticket->order->marketplaceCustomer;
        $seatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
        $orderSource = $ticket->order->source ?? 'online';

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->ticketType?->name,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'seat_label' => $ticket->seat_label,
                'section' => $seatDetails['section_name'] ?? null,
                'row' => $seatDetails['row_label'] ?? null,
                'seat' => $seatDetails['seat_number'] ?? null,
                'attendee_name' => $ticket->attendee_name,
            ],
            'customer' => [
                'name' => $customer
                    ? $customer->first_name . ' ' . $customer->last_name
                    : $ticket->order->customer_name,
                'email' => $customer?->email ?? $ticket->order->customer_email,
            ],
            'order' => [
                'source' => $orderSource,
                'customer_name' => $ticket->order->customer_name,
            ],
            'venue_notes' => $venueNotes,
        ], 'Ticket checked in successfully');
    }

    /**
     * Check in a ticket
     */
    public function checkIn(Request $request, int $eventId, string $barcode): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Count tickets from paid/confirmed/completed orders (confirmed = POS cash)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        $ticket = \App\Models\Ticket::where('barcode', $barcode)
            ->whereHas('order', function ($q) use ($event, $validOrderStatuses) {
                $q->where('event_id', $event->id)
                    ->whereIn('status', $validOrderStatuses);
            })
            ->first();

        // Fallback: search external tickets if not found in main tickets table
        if (!$ticket) {
            return $this->checkInExternalTicket($barcode, [$event->id], $organizer);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        // Venue-owner private notes (visible to marketplace scanners per product requirement)
        $venueNotes = $this->resolveVenueNotesForTicket($ticket);

        if ($ticket->checked_in_at) {
            $dupCustomer = $ticket->order->marketplaceCustomer;
            $dupSeatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
            return response()->json([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
                'ticket' => [
                    'barcode' => $ticket->barcode,
                    'ticket_type' => $ticket->ticketType?->name,
                    'checked_in_at' => $ticket->checked_in_at->toIso8601String(),
                    'checked_in_by' => $ticket->checked_in_by,
                    'section' => $dupSeatDetails['section_name'] ?? null,
                    'row' => $dupSeatDetails['row_label'] ?? null,
                    'seat' => $dupSeatDetails['seat_number'] ?? null,
                    'attendee_name' => $ticket->attendee_name,
                ],
                'customer' => [
                    'name' => $dupCustomer
                        ? $dupCustomer->first_name . ' ' . $dupCustomer->last_name
                        : $ticket->order->customer_name,
                ],
                'order' => [
                    'source' => $ticket->order->source ?? 'online',
                    'customer_name' => $ticket->order->customer_name,
                ],
                'venue_notes' => $venueNotes,
            ], 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $organizer->contact_name ?? $organizer->name,
        ]);

        $customer = $ticket->order->marketplaceCustomer;
        $seatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
        $orderSource = $ticket->order->source ?? 'online';

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->ticketType?->name,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'seat_label' => $ticket->seat_label,
                'section' => $seatDetails['section_name'] ?? null,
                'row' => $seatDetails['row_label'] ?? null,
                'seat' => $seatDetails['seat_number'] ?? null,
                'attendee_name' => $ticket->attendee_name,
            ],
            'customer' => [
                'name' => $customer
                    ? $customer->first_name . ' ' . $customer->last_name
                    : $ticket->order->customer_name,
                'email' => $customer?->email ?? $ticket->order->customer_email,
            ],
            'order' => [
                'source' => $orderSource,
                'customer_name' => $ticket->order->customer_name,
            ],
            'venue_notes' => $venueNotes,
        ], 'Ticket checked in successfully');
    }

    /**
     * Undo check-in for a ticket
     */
    public function undoCheckIn(Request $request, int $eventId, string $barcode): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $ticket = \App\Models\Ticket::where('barcode', $barcode)
            ->whereHas('order', function ($q) use ($event) {
                $q->where('event_id', $event->id)
                    ->whereIn('status', ['paid', 'confirmed', 'completed']);
            })
            ->first();

        // Fallback: check external tickets
        if (!$ticket) {
            $extTicket = \App\Models\ExternalTicket::where('barcode', $barcode)
                ->where('event_id', $event->id)
                ->first();

            if (!$extTicket) {
                return $this->error('Ticket not found', 404);
            }
            if (!$extTicket->checked_in_at) {
                return $this->error('Ticket is not checked in', 400);
            }
            $extTicket->update(['checked_in_at' => null, 'checked_in_by' => null]);
            return $this->success(null, 'Check-in undone');
        }

        if (!$ticket->checked_in_at) {
            return $this->error('Ticket is not checked in', 400);
        }

        $ticket->update([
            'checked_in_at' => null,
            'checked_in_by' => null,
        ]);

        return $this->success(null, 'Check-in undone');
    }

    /**
     * Check in an external ticket (imported from other ticketing platforms).
     * Used as a fallback when ticket is not found in the main tickets table.
     */
    protected function checkInExternalTicket(string $barcode, array $eventIds, $organizer): JsonResponse
    {
        $extTicket = \App\Models\ExternalTicket::where('barcode', $barcode)
            ->whereIn('event_id', $eventIds)
            ->first();

        if (!$extTicket) {
            return $this->error('Ticket not found or invalid', 404);
        }

        if ($extTicket->status === 'cancelled') {
            return $this->error('This ticket has been cancelled', 400);
        }

        if ($extTicket->checked_in_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $extTicket->checked_in_at->format('Y-m-d H:i:s'),
                'ticket' => [
                    'barcode' => $extTicket->barcode,
                    'ticket_type' => $extTicket->ticket_type_name,
                    'checked_in_at' => $extTicket->checked_in_at->toIso8601String(),
                    'checked_in_by' => $extTicket->checked_in_by,
                    'attendee_name' => $extTicket->attendee_name,
                ],
                'customer' => [
                    'name' => $extTicket->attendee_name,
                ],
                'order' => [
                    'source' => 'external',
                    'customer_name' => $extTicket->attendee_name,
                ],
            ], 400);
        }

        $extTicket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $organizer->contact_name ?? $organizer->name,
            'status' => 'used',
        ]);

        return $this->success([
            'ticket' => [
                'id' => $extTicket->id,
                'barcode' => $extTicket->barcode,
                'ticket_type' => $extTicket->ticket_type_name,
                'status' => 'used',
                'checked_in_at' => $extTicket->checked_in_at->toIso8601String(),
                'attendee_name' => $extTicket->attendee_name,
            ],
            'customer' => [
                'name' => $extTicket->attendee_name,
                'email' => $extTicket->attendee_email,
            ],
            'order' => [
                'source' => 'external',
                'customer_name' => $extTicket->attendee_name,
            ],
        ], 'Ticket checked in successfully');
    }

    /**
     * Export participants list as CSV
     */
    public function exportParticipants(Request $request, int $eventId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            abort(404, 'Event not found');
        }

        $tickets = \App\Models\Ticket::whereHas('order', function ($q) use ($event) {
                $q->where('event_id', $event->id)
                    ->whereIn('status', ['paid', 'confirmed', 'completed']);
            })
            ->with(['order.marketplaceCustomer', 'ticketType'])
            ->get();

        $filename = 'participants-' . $event->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, [
                'Ticket ID',
                'Barcode',
                'Ticket Type',
                'Price',
                'Customer Name',
                'Customer Phone',
                'Order Number',
                'Purchased At',
                'Checked In',
                'Checked In At',
            ]);

            foreach ($tickets as $ticket) {
                $customer = $ticket->order->marketplaceCustomer;
                $ticketType = $ticket->ticketType;
                fputcsv($handle, [
                    $ticket->id,
                    $ticket->barcode,
                    $ticketType?->name ?? 'N/A',
                    $ticketType?->display_price ?? 0,
                    $customer ? $customer->first_name . ' ' . $customer->last_name : $ticket->order->customer_name,
                    $customer?->phone ?? $ticket->order->customer_phone,
                    $ticket->order->order_number,
                    $ticket->created_at->format('Y-m-d H:i:s'),
                    $ticket->checked_in_at ? 'Yes' : 'No',
                    $ticket->checked_in_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export event report as PDF
     */
    public function exportReport(Request $request, int $eventId): \Illuminate\Http\Response
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->with(['ticketTypes', 'venue', 'marketplaceCity'])
            ->first();

        if (!$event) {
            abort(404, 'Event not found');
        }

        // Get completed orders
        $completedOrders = $event->orders()->whereIn('status', ['paid', 'confirmed', 'completed'])->get();

        // Calculate statistics
        $totalTicketsSold = $event->tickets()
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['paid', 'confirmed', 'completed']);
            })->count();

        $totalRevenue = $completedOrders->sum('total');
        $totalOrders = $completedOrders->count();

        // Check-in stats
        $checkedInCount = $event->tickets()
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['paid', 'confirmed', 'completed']);
            })
            ->whereNotNull('checked_in_at')
            ->count();

        // Ticket types breakdown
        $ticketTypeStats = $event->ticketTypes->map(function ($tt) use ($event) {
            $sold = $event->tickets()
                ->where('ticket_type_id', $tt->id)
                ->whereHas('order', function ($q) {
                    $q->whereIn('status', ['paid', 'confirmed', 'completed']);
                })->count();

            return [
                'name' => $tt->name,
                'price' => $tt->display_price,
                'quota' => $tt->quota_total,
                'sold' => $sold,
                'revenue' => $sold * $tt->display_price,
            ];
        });

        // Daily sales breakdown (last 30 days)
        $dailySales = $completedOrders
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(function ($order) {
                return $order->created_at->format('Y-m-d');
            })
            ->map(function ($orders, $date) {
                return [
                    'date' => $date,
                    'orders' => $orders->count(),
                    'revenue' => $orders->sum('total'),
                    'tickets' => $orders->sum(fn ($o) => $o->tickets->count()),
                ];
            })
            ->values();

        // Get localized title
        $eventTitle = $event->getTranslation('title', 'ro')
            ?: $event->getTranslation('title', 'en')
            ?: $event->getTranslation('title')
            ?: 'Event';

        $data = [
            'event' => $event,
            'eventTitle' => $eventTitle,
            'organizer' => $organizer,
            'stats' => [
                'total_tickets_sold' => $totalTicketsSold,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'checked_in' => $checkedInCount,
                'check_in_rate' => $totalTicketsSold > 0 ? round(($checkedInCount / $totalTicketsSold) * 100, 1) : 0,
            ],
            'ticket_types' => $ticketTypeStats,
            'daily_sales' => $dailySales,
            'generated_at' => now()->format('d.m.Y H:i'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.organizer-event-report', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'raport-' . Str::slug($eventTitle) . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export all participants (across all events or per-event) with format support
     */
    public function exportAllParticipants(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $organizer = $this->requireOrganizer($request);

        $format = $request->get('format', 'csv');
        $eventId = $request->get('event_id');

        // Get event IDs
        $eventIds = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->pluck('id');

        // If event_id is provided, filter to that event only
        if ($eventId) {
            if (!$eventIds->contains($eventId)) {
                abort(404, 'Event not found');
            }
            $eventIds = collect([$eventId]);
        }

        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        $tickets = \App\Models\Ticket::whereHas('order', function ($q) use ($eventIds, $validOrderStatuses) {
                $q->whereIn('event_id', $eventIds)
                    ->whereIn('status', $validOrderStatuses);
            })
            ->with(['order.marketplaceCustomer', 'order.event', 'ticketType'])
            ->get();

        $rows = $tickets->map(function ($ticket) {
            $customer = $ticket->order->marketplaceCustomer;
            $event = $ticket->order->event;

            $eventTitle = 'Unknown Event';
            if ($event) {
                $eventTitle = $event->getTranslation('title', 'ro')
                    ?: $event->getTranslation('title', 'en')
                    ?: $event->getTranslation('title')
                    ?: 'Unknown Event';
            }

            return [
                'Ticket ID' => $ticket->id,
                'Barcode' => $ticket->barcode,
                'Control Code' => $ticket->code,
                'Event' => $eventTitle,
                'Ticket Type' => $ticket->ticketType?->name ?? 'Standard',
                'Price' => $ticket->ticketType?->display_price ?? 0,
                'Customer Name' => $customer ? $customer->first_name . ' ' . $customer->last_name : ($ticket->order->customer_name ?? 'N/A'),
                'Customer Phone' => $customer?->phone ?? $ticket->order->customer_phone ?? '',
                'Order Number' => $ticket->order->order_number,
                'Purchased At' => $ticket->created_at->format('Y-m-d H:i:s'),
                'Checked In' => $ticket->checked_in_at ? 'Da' : 'Nu',
                'Checked In At' => $ticket->checked_in_at?->format('Y-m-d H:i:s') ?? '',
            ];
        });

        $filename = 'participanti-' . now()->format('Y-m-d');

        if ($format === 'xlsx') {
            return $this->exportToXlsx($rows->toArray(), $filename);
        }

        return $this->exportToCsv($rows->toArray(), $filename);
    }

    /**
     * Export data to CSV
     */
    protected function exportToCsv(array $rows, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if (!empty($rows)) {
                // Headers
                fputcsv($handle, array_keys($rows[0]));

                // Data
                foreach ($rows as $row) {
                    fputcsv($handle, array_values($row));
                }
            }

            fclose($handle);
        }, $filename . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }

    /**
     * Export data to XLSX
     */
    protected function exportToXlsx(array $rows, string $filename): \Illuminate\Http\Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if (!empty($rows)) {
            // Headers
            $headers = array_keys($rows[0]);
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValue([$col, 1], $header);
                $sheet->getStyle([$col, 1])->getFont()->setBold(true);
                $col++;
            }

            // Data
            $rowNum = 2;
            foreach ($rows as $row) {
                $col = 1;
                foreach ($row as $value) {
                    $sheet->setCellValue([$col, $rowNum], $value);
                    $col++;
                }
                $rowNum++;
            }

            // Auto-size columns
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Get event statistics
     */
    public function statistics(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $validStatuses = ['paid', 'confirmed', 'completed'];
        $completedOrders = $event->orders()->whereIn('status', $validStatuses);

        $ticketStats = $event->ticketTypes()->get()->map(function ($tt) use ($event, $validStatuses) {
            $sold = $event->orders()
                ->whereIn('status', $validStatuses)
                ->whereHas('tickets', function ($q) use ($tt) {
                    $q->where('ticket_type_id', $tt->id);
                })
                ->withCount(['tickets' => function ($q) use ($tt) {
                    $q->where('ticket_type_id', $tt->id);
                }])
                ->get()
                ->sum('tickets_count');

            return [
                'id' => $tt->id,
                'name' => $tt->name,
                'price' => $tt->display_price,
                'quantity' => $tt->quota_total,
                'sold' => $sold,
                'available' => $tt->available_quantity,
                'revenue' => $sold * $tt->display_price,
            ];
        });

        $eventName = $this->getLocalizedTitle($event);
        $totalRevenue = (float) $event->total_revenue;

        // Use event's effective commission rate and mode (may be custom per-event or default from organizer)
        $effectiveCommissionRate = $event->getEffectiveCommissionRate();
        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionAmount = round($totalRevenue * $effectiveCommissionRate / 100, 2);

        // Net revenue depends on commission mode:
        // - "added_on_top": commission paid by customer extra, organizer gets full ticket price
        // - "included": commission deducted from ticket price
        $netRevenue = $commissionMode === 'added_on_top'
            ? $totalRevenue
            : round($totalRevenue - $commissionAmount, 2);

        return $this->success([
            'event_id' => $event->id,
            'event_name' => $eventName,
            'summary' => [
                'total_orders' => $completedOrders->count(),
                'total_tickets_sold' => $event->total_tickets_sold,
                'gross_revenue' => $totalRevenue,
                'commission_rate' => $effectiveCommissionRate,
                'commission_mode' => $commissionMode,
                'use_fixed_commission' => (bool) $event->use_fixed_commission,
                'commission_amount' => $commissionAmount,
                'net_revenue' => $netRevenue,
                'views' => $event->views_count ?? 0,
            ],
            'ticket_types' => $ticketStats,
            'sales_by_day' => $completedOrders
                ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }

    /**
     * Get event analytics (comprehensive dashboard data)
     */
    public function analytics(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $period = $request->get('period', '30d');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Determine date range
        $now = now();
        $eventCreatedAt = $event->created_at->startOfDay();

        if ($startDate && $endDate) {
            $rangeStart = Carbon::parse($startDate)->startOfDay();
            $rangeEnd = Carbon::parse($endDate)->endOfDay();
        } else {
            $rangeEnd = $now;
            $rangeStart = match ($period) {
                '7d' => $now->copy()->subDays(7)->startOfDay(),
                '30d' => $now->copy()->subDays(30)->startOfDay(),
                '90d' => $now->copy()->subDays(90)->startOfDay(),
                'all' => $eventCreatedAt,
                default => $now->copy()->subDays(30)->startOfDay(),
            };
        }

        // Limit rangeStart to not be earlier than event creation date
        if ($rangeStart < $eventCreatedAt) {
            $rangeStart = $eventCreatedAt;
        }

        // Valid order statuses for analytics (only truly paid/completed)
        $validStatuses = ['paid', 'completed'];

        // Scoped orders query: only this organizer's marketplace orders
        $scopedOrders = fn () => $event->orders()
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', $validStatuses);

        // Base query for orders in the range (used for chart data & period comparisons)
        $ordersQuery = $scopedOrders()->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        // Overview metrics — always all-time (not filtered by period)
        $allTimeQuery = $scopedOrders();
        $grossRevenue = (float) (clone $allTimeQuery)->sum('total');

        // Per-ticket-type net price (organizer's take per ticket, with commission
        // logic applied by mode). Used below for net revenue and ticket_performance.
        $defaultRate = (float) ($event->commission_rate
            ?? $event->marketplaceOrganizer?->commission_rate
            ?? $event->marketplaceClient?->commission_rate
            ?? 5);
        $defaultMode = $event->commission_mode
            ?? $event->marketplaceOrganizer?->default_commission_mode
            ?? $event->marketplaceClient?->commission_mode
            ?? 'included';
        $netPricePerTicket = function (\App\Models\TicketType $tt) use ($defaultRate, $defaultMode): float {
            $basePriceCents = ((int) ($tt->sale_price_cents ?? 0)) > 0
                ? (int) $tt->sale_price_cents
                : (int) ($tt->price_cents ?? 0);
            $basePrice = $basePriceCents / 100;
            $effective = $tt->getEffectiveCommission($defaultRate, $defaultMode);
            $mode = $effective['mode'];
            // on_top: organizer keeps the full base price (commission is charged on top to the customer)
            // included: organizer keeps base minus the included commission portion
            if (in_array($mode, ['on_top', 'added_on_top'], true)) {
                return $basePrice;
            }
            $commPerTicket = (float) $tt->calculateCommission($basePrice, $defaultRate, $defaultMode);
            return max(0.0, $basePrice - $commPerTicket);
        };

        // Broad valid-ticket filter for this event — includes POS tickets and
        // invitations (which may have no order, and sometimes no event_id
        // either — they're linked only via ticket_type_id). Excludes external
        // imports and cancelled/refunded tickets.
        $eventTicketTypeIds = $event->ticketTypes->pluck('id')->toArray();
        $validEventTicketsQuery = fn () => \App\Models\Ticket::where(function ($q) use ($event, $eventTicketTypeIds) {
                $q->where('event_id', $event->id)
                  ->orWhere('marketplace_event_id', $event->id);
                if (!empty($eventTicketTypeIds)) {
                    $q->orWhereIn('ticket_type_id', $eventTicketTypeIds);
                }
            })
            ->whereIn('status', ['valid', 'used'])
            ->where(function ($q) {
                $q->whereDoesntHave('order')
                  ->orWhereHas('order', fn ($qq) => $qq->where('source', '!=', 'external_import'));
            });

        // Valid tickets grouped by ticket_type — used for net revenue and per-type counts
        $validCountsByType = (clone $validEventTicketsQuery())
            ->selectRaw('ticket_type_id, COUNT(*) as cnt')
            ->groupBy('ticket_type_id')
            ->pluck('cnt', 'ticket_type_id');

        // Net revenue = sum over VALID tickets of the ticket_type's net price
        // (uses the ticket_type's configured price, ignoring individual ticket.price
        // which can be 0 for invitations / free tickets).
        $netRevenue = 0.0;
        foreach ($event->ticketTypes as $tt) {
            $validCount = (int) ($validCountsByType[$tt->id] ?? 0);
            if ($validCount === 0) continue;
            $netRevenue += $netPricePerTicket($tt) * $validCount;
        }
        $netRevenue = round($netRevenue, 2);
        $commissionAmount = round($grossRevenue - $netRevenue, 2);

        // Refunds
        $refundsTotal = (float) Order::where('event_id', $event->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->sum('total');

        $totalRevenue = $grossRevenue; // Keep for backwards compat (chart/comparison)
        // Bilete vândute = all valid tickets (online + POS + invitations)
        $ticketsSold = (int) (clone $validEventTicketsQuery())->count();
        $pageViews = $event->views_count ?? 0;
        $conversionRate = $pageViews > 0 ? round(($ticketsSold / $pageViews) * 100, 2) : 0;

        // Tickets sold today (uses same broad filter as total count)
        $ticketsToday = (int) (clone $validEventTicketsQuery())
            ->whereDate('created_at', today())
            ->count();

        // Capacity from ticket types
        $capacity = $event->ticketTypes()->sum('quota_total') ?: ($event->capacity ?? 0);

        // Previous period for comparison
        $periodDays = max(1, $rangeStart->diffInDays($rangeEnd));
        $prevStart = $rangeStart->copy()->subDays($periodDays);
        $prevEnd = $rangeStart->copy()->subSecond();

        $prevOrdersQuery = $scopedOrders()
            ->whereBetween('created_at', [$prevStart, $prevEnd]);
        $prevRevenue = (float) $prevOrdersQuery->sum('total');
        $prevTickets = (int) $prevOrdersQuery->withCount('tickets')->get()->sum('tickets_count');

        // Revenue change: handle case where previous period had 0
        if ($prevRevenue > 0) {
            $revenueChange = round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1);
        } elseif ($totalRevenue > 0) {
            $revenueChange = 100.0; // New revenue where there was none
        } else {
            $revenueChange = 0;
        }

        if ($prevTickets > 0) {
            $ticketsChange = round((($ticketsSold - $prevTickets) / $prevTickets) * 100, 1);
        } elseif ($ticketsSold > 0) {
            $ticketsChange = 100.0;
        } else {
            $ticketsChange = 0;
        }

        // Chart data - daily revenue, tickets, and views
        $chartLabels = [];
        $chartRevenue = [];
        $chartTickets = [];
        $chartViews = [];
        $chartRawDates = [];

        // First pass: collect daily ticket data to distribute views proportionally
        $dailyData = [];
        $currentDate = $rangeStart->copy();
        $totalDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);

        // Pre-index per-ticket-type net price once so the daily revenue loop
        // below doesn't recompute it per day.
        $ttNetById = [];
        foreach ($event->ticketTypes as $tt) {
            $ttNetById[$tt->id] = $netPricePerTicket($tt);
        }

        while ($currentDate <= $rangeEnd) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            // Daily counts use the SAME broad ticket filter as the overview
            // (includes POS + invitations) so daily totals roll up to Bilete vândute.
            $dayCountsByType = (clone $validEventTicketsQuery())
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->selectRaw('ticket_type_id, COUNT(*) as cnt')
                ->groupBy('ticket_type_id')
                ->pluck('cnt', 'ticket_type_id');

            $dayNetRevenue = 0.0;
            $dayTickets = 0;
            foreach ($dayCountsByType as $ttId => $cnt) {
                $net = (float) ($ttNetById[$ttId] ?? 0);
                $dayNetRevenue += $net * (int) $cnt;
                $dayTickets += (int) $cnt;
            }

            $dailyData[] = [
                'label' => $currentDate->format('M d'),
                'date' => $currentDate->format('Y-m-d'),
                'revenue' => round($dayNetRevenue, 2),
                'tickets' => $dayTickets,
            ];

            $currentDate->addDay();
        }

        // Calculate views distribution based on ticket sales activity
        // If no ticket activity, distribute views showing a cumulative growth pattern
        $totalTicketsInRange = array_sum(array_column($dailyData, 'tickets'));

        foreach ($dailyData as $index => $day) {
            $chartLabels[] = $day['label'];
            $chartRawDates[] = $day['date'];
            $chartRevenue[] = $day['revenue'];
            $chartTickets[] = $day['tickets'];

            // Distribute views proportionally to ticket sales, or show cumulative if no sales
            if ($totalTicketsInRange > 0 && $day['tickets'] > 0) {
                // Days with ticket sales get proportional views
                $chartViews[] = max(1, intval($pageViews * ($day['tickets'] / $totalTicketsInRange)));
            } elseif ($totalTicketsInRange === 0 && $pageViews > 0) {
                // No ticket sales - show views as cumulative growth
                // Distribute views with a gradual increase towards the end of the period
                $progress = ($index + 1) / $totalDays;
                $cumulativeViews = intval($pageViews * $progress);
                $previousCumulative = $index > 0 ? intval($pageViews * ($index / $totalDays)) : 0;
                $dailyViews = max(0, $cumulativeViews - $previousCumulative);
                // Ensure at least some views show on the last day
                if ($index === count($dailyData) - 1 && $dailyViews === 0 && $pageViews > 0) {
                    $dailyViews = $pageViews - array_sum($chartViews);
                }
                $chartViews[] = max(0, $dailyViews);
            } else {
                $chartViews[] = 0;
            }
        }

        // Traffic sources from real tracking data (CoreCustomerEvent)
        $trafficSources = [];
        $trackingQuery = \App\Models\Platform\CoreCustomerEvent::where(function ($q) use ($event) {
            $q->where('event_id', $event->id)
              ->orWhere('marketplace_event_id', $event->id);
        })->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        if ($trackingQuery->exists()) {
            $sourceCase = "CASE
                WHEN fbclid IS NOT NULL OR utm_source = 'facebook' THEN 'Facebook'
                WHEN gclid IS NOT NULL OR utm_source = 'google' THEN 'Google'
                WHEN utm_source = 'instagram' OR referrer LIKE '%instagram%' THEN 'Instagram'
                WHEN ttclid IS NOT NULL OR utm_source = 'tiktok' THEN 'TikTok'
                WHEN utm_medium = 'email' THEN 'Email'
                WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                ELSE 'Organic'
            END";

            $trafficSources = (clone $trackingQuery)
                ->selectRaw("{$sourceCase} as source, COUNT(DISTINCT visitor_id) as visitors, SUM(CASE WHEN event_type = 'purchase' THEN event_value ELSE 0 END) as revenue")
                ->groupByRaw($sourceCase)
                ->orderByDesc('visitors')
                ->get()
                ->toArray();
        }

        // Ticket performance with trend and conversion
        $ticketPerformance = $event->ticketTypes->map(function ($tt) use ($event, $organizer, $rangeStart, $rangeEnd, $periodDays, $pageViews, $netPricePerTicket) {
            // Broad filter: all valid/used tickets of this tt. Scoping by
            // ticket_type_id is already event-scoped (tt belongs to the event).
            // No event_id check — invitations sometimes have NULL event_id.
            $ticketQuery = fn () => \App\Models\Ticket::where('ticket_type_id', $tt->id)
                ->whereIn('status', ['valid', 'used'])
                ->where(function ($q) {
                    $q->whereDoesntHave('order')
                      ->orWhereHas('order', fn ($qq) => $qq->where('source', '!=', 'external_import'));
                });

            $sold = (clone $ticketQuery())->count();

            // Revenue = valid_count × ticket_type's net price.
            $revenue = round($netPricePerTicket($tt) * $sold, 2);

            // Classification flags consumed by the UI to annotate the ticket-type name
            $ttMeta = is_array($tt->meta) ? $tt->meta : [];
            $isInvitation = ($tt->name === 'Invitatie') || (bool) ($ttMeta['is_invitation'] ?? false);
            $isEntryTicket = (bool) $tt->is_entry_ticket;

            // Trend: compare sales in current period vs previous period
            $currentPeriodSold = $ticketQuery()
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->count();

            $prevPeriodSold = $ticketQuery()
                ->whereBetween('created_at', [
                    $rangeStart->copy()->subDays($periodDays),
                    $rangeStart->copy()->subSecond(),
                ])
                ->count();

            if ($prevPeriodSold > 0) {
                $trend = round((($currentPeriodSold - $prevPeriodSold) / $prevPeriodSold) * 100, 0);
            } elseif ($currentPeriodSold > 0) {
                $trend = 100; // New sales where there were none
            } else {
                $trend = 0;
            }

            // Conversion rate: tickets sold / page views (simplified)
            $convRate = $pageViews > 0 ? round(($sold / $pageViews) * 100, 1) : 0;

            return [
                'name' => $tt->name,
                'price' => $tt->display_price,
                'sold' => $sold,
                'revenue' => $revenue,
                'capacity' => $tt->quota_total,
                'trend' => $trend,
                'conversion_rate' => $convRate,
                'is_entry_ticket' => $isEntryTicket,
                'is_invitation' => $isInvitation,
            ];
        });

        // Top locations - empty until real location tracking is implemented
        // Real data would come from a visitor_logs or analytics table with geolocation
        $topLocations = [];

        // Recent sales
        $recentSales = $scopedOrders()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                $customer = $order->marketplaceCustomer;
                return [
                    'buyer_name' => $customer
                        ? $customer->first_name . ' ' . substr($customer->last_name ?? '', 0, 1) . '.'
                        : ($order->customer_name ?? 'Client'),
                    'time_ago' => $order->created_at->diffForHumans(),
                    'amount' => (float) $order->total,
                    'tickets' => $order->tickets->count(),
                ];
            });

        $eventName = $this->getLocalizedTitle($event);

        // Calculate days until event
        $eventDate = $this->getStartsAt($event);
        $daysUntil = null;
        if ($eventDate) {
            $eventDateTime = Carbon::parse($eventDate);
            $daysUntil = (int) now()->startOfDay()->diffInDays($eventDateTime->startOfDay(), false);
        }

        return $this->success([
            'event' => [
                'id' => $event->id,
                'title' => $eventName,
                'starts_at' => $this->getStartsAt($event),
                'date' => $this->getStartsAt($event),
                'venue' => $event->venue?->name ?? null,
                'venue_city' => $event->venue?->city ?? $event->marketplaceCity?->name ?? null,
                'image' => $this->getStorageUrl($event->poster_url),
                'is_cancelled' => $event->is_cancelled,
                'is_sold_out' => $event->is_sold_out ?? false,
                'days_until' => $daysUntil,
                'created_at' => $event->created_at?->toIso8601String(),
                'ends_at' => $event->event_end_date ? Carbon::parse($event->event_end_date)->toIso8601String() : null,
            ],
            'overview' => [
                'total_revenue' => $netRevenue,
                'gross_revenue' => $grossRevenue,
                'net_revenue' => $netRevenue,
                'commission_amount' => $commissionAmount,
                'refunds_total' => $refundsTotal,
                'tickets_sold' => $ticketsSold,
                'tickets_today' => $ticketsToday,
                'capacity' => $capacity,
                'page_views' => $pageViews,
                'unique_visitors' => $pageViews,
                'conversion_rate' => $conversionRate,
                'revenue_change' => $revenueChange,
                'tickets_change' => $ticketsChange,
                'views_change' => 0,
                'days_until' => $daysUntil,
                'commission_rate' => $event->getEffectiveCommissionRate(),
                'commission_mode' => $event->getEffectiveCommissionMode(),
                'use_fixed_commission' => (bool) $event->use_fixed_commission,
            ],
            'chart' => [
                'labels' => $chartLabels,
                'raw_dates' => $chartRawDates,
                'revenue' => $chartRevenue,
                'tickets' => $chartTickets,
                'views' => $chartViews,
            ],
            'traffic_sources' => $trafficSources,
            'ticket_performance' => $ticketPerformance,
            'top_locations' => $topLocations,
            'recent_sales' => $recentSales,
        ]);
    }

    /**
     * Get available event categories for the marketplace
     */
    public function categories(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $categories = \App\Models\MarketplaceEventCategory::where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($cat) {
                $name = is_array($cat->name) ? ($cat->name['ro'] ?? $cat->name['en'] ?? array_values($cat->name)[0] ?? '') : $cat->name;
                return [
                    'id' => $cat->id,
                    'name' => $name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon,
                    'icon_emoji' => $cat->icon_emoji,
                    'event_type_ids' => $cat->event_type_ids ?? [],
                ];
            });

        return $this->success(['categories' => $categories]);
    }

    /**
     * Get event genres filtered by event type IDs
     */
    public function genres(Request $request): JsonResponse
    {
        $this->requireOrganizer($request);

        $typeIds = $request->input('type_ids', []);
        if (!is_array($typeIds) || empty($typeIds)) {
            return $this->success(['genres' => []]);
        }

        $genres = \App\Models\EventGenre::query()
            ->whereExists(function ($sub) use ($typeIds) {
                $sub->selectRaw('1')
                    ->from('event_type_event_genre as eteg')
                    ->whereColumn('eteg.event_genre_id', 'event_genres.id')
                    ->whereIn('eteg.event_type_id', $typeIds);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($genre) {
                $name = is_array($genre->name) ? ($genre->name['ro'] ?? $genre->name['en'] ?? array_values($genre->name)[0] ?? '') : $genre->name;
                return [
                    'id' => $genre->id,
                    'name' => $name,
                    'slug' => $genre->slug,
                ];
            });

        return $this->success(['genres' => $genres]);
    }

    /**
     * Search venues available for the marketplace
     */
    public function venues(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $search = $request->input('search', '');

        $query = \App\Models\Venue::query()
            ->where(function ($q) use ($organizer) {
                $q->whereNull('marketplace_client_id')
                    ->orWhere('marketplace_client_id', $organizer->marketplace_client_id);
            });

        if (strlen($search) >= 2) {
            $searchLower = mb_strtolower($search);
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(city) LIKE ?', ["%{$searchLower}%"])
                    ->orWhereRaw('LOWER(address) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        $venues = $query
            ->orderByRaw('CASE WHEN marketplace_client_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function ($venue) use ($organizer) {
                $name = is_array($venue->name) ? ($venue->name['ro'] ?? $venue->name['en'] ?? array_values($venue->name)[0] ?? '') : $venue->name;
                return [
                    'id' => $venue->id,
                    'name' => $name,
                    'city' => $venue->city,
                    'address' => $venue->address,
                    'capacity' => $venue->capacity,
                    'website_url' => $venue->website_url,
                    'is_marketplace' => $venue->marketplace_client_id === $organizer->marketplace_client_id,
                ];
            });

        return $this->success(['venues' => $venues]);
    }

    /**
     * Search artists available for the marketplace
     */
    public function artists(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $search = $request->input('search', '');

        $query = \App\Models\Artist::query()
            ->where(function ($q) use ($organizer) {
                $q->whereNull('marketplace_client_id')
                    ->orWhere('marketplace_client_id', $organizer->marketplace_client_id);
            })
            ->where('is_active', true);

        if (strlen($search) >= 2) {
            $query->where('name', 'like', "%{$search}%");
        }

        $artists = $query->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'slug' => $artist->slug,
                    'image' => $artist->main_image_url,
                ];
            });

        return $this->success(['artists' => $artists]);
    }

    /**
     * Create a new artist for the marketplace
     */
    public function storeArtist(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $artist = \App\Models\Artist::create([
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'is_active' => true,
        ]);

        return $this->success([
            'artist' => [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'image' => null,
            ],
        ], 'Artist created', 201);
    }

    /**
     * Get goals for an event
     */
    public function goals(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $goals = EventGoal::forEvent($eventId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($goal) {
                // Update progress before returning
                $goal->updateProgress();

                return [
                    'id' => $goal->id,
                    'type' => $goal->type,
                    'type_label' => $goal->type_label,
                    'type_icon' => $goal->type_icon,
                    'type_color' => $goal->type_color,
                    'name' => $goal->name,
                    'target_value' => $goal->target_value,
                    'current_value' => $goal->current_value,
                    'formatted_target' => $goal->formatted_target,
                    'formatted_current' => $goal->formatted_current,
                    'progress_percent' => (float) $goal->progress_percent,
                    'progress_status' => $goal->progress_status,
                    'deadline' => $goal->deadline?->toDateString(),
                    'days_remaining' => $goal->days_remaining,
                    'remaining' => $goal->remaining,
                    'status' => $goal->status,
                    'is_achieved' => $goal->isAchieved(),
                    'is_overdue' => $goal->isOverdue(),
                    'achieved_at' => $goal->achieved_at?->toIso8601String(),
                    'notes' => $goal->notes,
                    'created_at' => $goal->created_at->toIso8601String(),
                ];
            });

        return $this->success([
            'goals' => $goals,
            'types' => EventGoal::TYPE_CONFIG,
        ]);
    }

    /**
     * Create a new goal for an event
     */
    public function storeGoal(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:revenue,tickets,visitors,conversion_rate',
            'name' => 'required|string|max:255',
            'target_value' => 'required|numeric|min:1',
            'deadline' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'email_alerts' => 'nullable|boolean',
            'in_app_alerts' => 'nullable|boolean',
            'alert_email' => 'nullable|email',
        ]);

        // Convert target value to appropriate format (cents for revenue, percentage * 100 for conversion)
        $targetValue = match ($validated['type']) {
            'revenue' => (int) ($validated['target_value'] * 100), // Convert to cents
            'conversion_rate' => (int) ($validated['target_value'] * 100), // Convert percentage to integer
            default => (int) $validated['target_value'],
        };

        $goal = EventGoal::create([
            'event_id' => $eventId,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'target_value' => $targetValue,
            'current_value' => 0,
            'progress_percent' => 0,
            'deadline' => $validated['deadline'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'email_alerts' => $validated['email_alerts'] ?? false,
            'in_app_alerts' => $validated['in_app_alerts'] ?? true,
            'alert_email' => $validated['alert_email'] ?? $organizer->email,
            'alert_thresholds' => EventGoal::DEFAULT_THRESHOLDS,
            'status' => EventGoal::STATUS_ACTIVE,
        ]);

        // Update progress immediately
        $goal->updateProgress();

        return $this->success([
            'goal' => [
                'id' => $goal->id,
                'type' => $goal->type,
                'type_label' => $goal->type_label,
                'name' => $goal->name,
                'target_value' => $goal->target_value,
                'current_value' => $goal->current_value,
                'formatted_target' => $goal->formatted_target,
                'formatted_current' => $goal->formatted_current,
                'progress_percent' => (float) $goal->progress_percent,
                'deadline' => $goal->deadline?->toDateString(),
                'status' => $goal->status,
            ],
        ], 'Goal created', 201);
    }

    /**
     * Update a goal
     */
    public function updateGoal(Request $request, int $eventId, int $goalId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $goal = EventGoal::where('id', $goalId)
            ->where('event_id', $eventId)
            ->first();

        if (!$goal) {
            return $this->error('Goal not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'target_value' => 'sometimes|numeric|min:1',
            'deadline' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'email_alerts' => 'nullable|boolean',
            'in_app_alerts' => 'nullable|boolean',
            'status' => 'sometimes|string|in:active,achieved,missed,cancelled',
        ]);

        if (isset($validated['target_value'])) {
            $validated['target_value'] = match ($goal->type) {
                'revenue' => (int) ($validated['target_value'] * 100),
                'conversion_rate' => (int) ($validated['target_value'] * 100),
                default => (int) $validated['target_value'],
            };
        }

        $goal->update($validated);
        $goal->updateProgress();

        return $this->success([
            'goal' => [
                'id' => $goal->id,
                'type' => $goal->type,
                'name' => $goal->name,
                'target_value' => $goal->target_value,
                'progress_percent' => (float) $goal->progress_percent,
                'status' => $goal->status,
            ],
        ], 'Goal updated');
    }

    /**
     * Delete a goal
     */
    public function deleteGoal(Request $request, int $eventId, int $goalId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $goal = EventGoal::where('id', $goalId)
            ->where('event_id', $eventId)
            ->first();

        if (!$goal) {
            return $this->error('Goal not found', 404);
        }

        $goal->delete();

        return $this->success(null, 'Goal deleted');
    }

    /**
     * Get milestones/campaigns for an event
     */
    public function milestones(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $milestones = EventMilestone::forEvent($eventId)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($milestone) {
                return [
                    'id' => $milestone->id,
                    'type' => $milestone->type,
                    'type_label' => $milestone->getTypeLabel(),
                    'type_icon' => $milestone->getTypeIcon(),
                    'type_color' => $milestone->getTypeColor(),
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'start_date' => $milestone->start_date?->toDateString(),
                    'end_date' => $milestone->end_date?->toDateString(),
                    'budget' => (float) $milestone->budget,
                    'currency' => $milestone->currency ?? 'RON',
                    'is_ad_campaign' => $milestone->isAdCampaign(),
                    'has_budget' => $milestone->hasBudget(),
                    'impressions' => $milestone->impressions,
                    'clicks' => $milestone->clicks,
                    'conversions' => $milestone->conversions,
                    'attributed_revenue' => (float) $milestone->attributed_revenue,
                    'roi' => (float) $milestone->roi,
                    'roas' => (float) $milestone->roas,
                    'cac' => (float) $milestone->cac,
                    'utm_source' => $milestone->utm_source,
                    'utm_medium' => $milestone->utm_medium,
                    'utm_campaign' => $milestone->utm_campaign,
                    'is_active' => $milestone->is_active,
                    'created_at' => $milestone->created_at->toIso8601String(),
                ];
            });

        return $this->success([
            'milestones' => $milestones,
            'types' => EventMilestone::TYPE_LABELS,
        ]);
    }

    /**
     * Create a new milestone/campaign for an event
     */
    public function storeMilestone(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:campaign_fb,campaign_google,campaign_tiktok,campaign_instagram,campaign_other,email,price,announcement,press,lineup,custom',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'platform_campaign_id' => 'nullable|string|max:100',
            'attribution_window_days' => 'nullable|integer|min:1|max:90',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'impact_metric' => 'nullable|string|max:50',
            'baseline_value' => 'nullable|numeric',
            'post_value' => 'nullable|numeric',
        ]);

        $milestone = EventMilestone::create([
            'event_id' => $eventId,
            'marketplace_client_id' => $organizer->marketplace_client_id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'budget' => $validated['budget'] ?? null,
            'currency' => $validated['currency'] ?? 'RON',
            'platform_campaign_id' => $validated['platform_campaign_id'] ?? null,
            'attribution_window_days' => $validated['attribution_window_days'] ?? 7,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
            'utm_content' => $validated['utm_content'] ?? null,
            'utm_term' => $validated['utm_term'] ?? null,
            'impact_metric' => $validated['impact_metric'] ?? null,
            'baseline_value' => $validated['baseline_value'] ?? null,
            'post_value' => $validated['post_value'] ?? null,
            'is_active' => true,
        ]);

        // Auto-generate UTM parameters if not provided
        if (!$milestone->utm_campaign) {
            $milestone->autoGenerateUtmParameters();
            $milestone->save();
        }

        return $this->success([
            'milestone' => [
                'id' => $milestone->id,
                'type' => $milestone->type,
                'type_label' => $milestone->getTypeLabel(),
                'title' => $milestone->title,
                'start_date' => $milestone->start_date?->toDateString(),
                'end_date' => $milestone->end_date?->toDateString(),
                'budget' => (float) $milestone->budget,
                'utm_campaign' => $milestone->utm_campaign,
            ],
        ], 'Milestone created', 201);
    }

    /**
     * Update a milestone
     */
    public function updateMilestone(Request $request, int $eventId, int $milestoneId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $milestone = EventMilestone::where('id', $milestoneId)
            ->where('event_id', $eventId)
            ->first();

        if (!$milestone) {
            return $this->error('Milestone not found', 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'impressions' => 'nullable|integer|min:0',
            'clicks' => 'nullable|integer|min:0',
            'conversions' => 'nullable|integer|min:0',
            'attributed_revenue' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $milestone->update($validated);

        // Recalculate metrics if budget or revenue changed
        if (isset($validated['budget']) || isset($validated['attributed_revenue']) || isset($validated['conversions'])) {
            $milestone->updateCalculatedMetrics();
        }

        return $this->success([
            'milestone' => [
                'id' => $milestone->id,
                'type' => $milestone->type,
                'title' => $milestone->title,
                'budget' => (float) $milestone->budget,
                'roi' => (float) $milestone->roi,
                'roas' => (float) $milestone->roas,
            ],
        ], 'Milestone updated');
    }

    /**
     * Delete a milestone
     */
    public function deleteMilestone(Request $request, int $eventId, int $milestoneId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $milestone = EventMilestone::where('id', $milestoneId)
            ->where('event_id', $eventId)
            ->first();

        if (!$milestone) {
            return $this->error('Milestone not found', 404);
        }

        $milestone->delete();

        return $this->success(null, 'Milestone deleted');
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }

    /**
     * Get localized title from Event model
     */
    protected function getLocalizedTitle(Event $event): string
    {
        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? array_values($title)[0] ?? '';
        }
        return $title ?? '';
    }

    /**
     * Get localized field from Event model
     */
    protected function getLocalizedField(Event $event, string $field): string
    {
        $value = $event->$field;
        if (is_array($value)) {
            return $value['ro'] ?? $value['en'] ?? array_values($value)[0] ?? '';
        }
        return $value ?? '';
    }

    /**
     * Get event status string for API response
     */
    protected function getEventStatus(Event $event): string
    {
        if ($event->is_cancelled) {
            return 'cancelled';
        }
        if ($event->is_postponed) {
            return 'postponed';
        }
        if (!$event->is_published) {
            return 'draft';
        }
        return 'published';
    }

    /**
     * Get starts_at datetime from Event model
     */
    protected function getStartsAt(Event $event): ?string
    {
        // Use start_date accessor which handles all duration modes (single_day, range, multi_day, recurring)
        $date = $event->start_date;
        if ($date) {
            $time = match ($event->duration_mode) {
                'range' => $event->range_start_time ?? $event->start_time ?? '00:00',
                default => $event->start_time ?? '00:00',
            };
            return Carbon::parse($date->format('Y-m-d') . ' ' . $time)->toIso8601String();
        }
        return null;
    }

    /**
     * Get ends_at datetime from Event model
     */
    protected function getEndsAt(Event $event): ?string
    {
        // Use end_date accessor for range/multi_day, fall back to start_date for single_day
        $endDate = $event->end_date ?? $event->start_date;
        if ($endDate) {
            $endTime = match ($event->duration_mode) {
                'range' => $event->range_end_time ?? $event->end_time ?? '23:59',
                default => $event->end_time ?? '23:59',
            };
            return Carbon::parse($endDate->format('Y-m-d') . ' ' . $endTime)->toIso8601String();
        }
        return null;
    }

    /**
     * Get doors_open_at datetime from Event model
     */
    protected function getDoorsAt(Event $event): ?string
    {
        if ($event->event_date && $event->door_time) {
            $date = $event->event_date->format('Y-m-d');
            return Carbon::parse("{$date} {$event->door_time}")->toIso8601String();
        }
        return null;
    }

    /**
     * Check if event is in the past (finished)
     */
    protected function isEventPast(Event $event): bool
    {
        $endDate = $this->getEndsAt($event);
        if (!$endDate) {
            // If no end date, use start date
            $endDate = $this->getStartsAt($event);
        }

        if (!$endDate) {
            return false;
        }

        return Carbon::parse($endDate)->isPast();
    }

    /**
     * Check if event can be edited
     * Events cannot be edited if they are:
     * - Finished (in the past)
     * - Cancelled
     */
    protected function isEventEditable(Event $event): bool
    {
        // Cannot edit if cancelled
        if ($event->is_cancelled) {
            return false;
        }

        // Cannot edit if event has finished
        if ($this->isEventPast($event)) {
            return false;
        }

        return true;
    }

    /**
     * Get price range string
     */
    protected function getPriceRange(Event $event): ?string
    {
        $prices = $event->ticketTypes->pluck('display_price')->filter()->values();
        if ($prices->isEmpty()) {
            return null;
        }
        $min = $prices->min();
        $max = $prices->max();
        if ($min === $max) {
            return number_format($min, 2) . ' RON';
        }
        return number_format($min, 2) . ' - ' . number_format($max, 2) . ' RON';
    }

    /**
     * Get seating map data for POS seat selection
     */
    public function seatingMap(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $eventModel = Event::where('id', $event)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        if (!$eventModel->seating_layout_id) {
            return $this->error('Event does not have a seating layout', 404);
        }

        // Get the published event seating layout
        $layout = \App\Models\Seating\EventSeatingLayout::where('event_id', $eventModel->id)
            ->published()
            ->latest('published_at')
            ->first();

        if (!$layout) {
            return $this->error('No published seating layout found', 404);
        }

        // Use pre-computed json_geometry (instant, no DB tree traversal)
        $geometry = $layout->json_geometry;
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }

        $canvas = $geometry['canvas'] ?? ['width' => 1000, 'height' => 800];
        $sections = $geometry['sections'] ?? [];

        // If geometry is missing section positions (old snapshot), fall back to DB
        if (!empty($sections) && !isset($sections[0]['x'])) {
            $seatingLayout = \App\Models\Seating\SeatingLayout::with(['sections'])->find($eventModel->seating_layout_id);
            if ($seatingLayout) {
                $canvas = ['width' => $seatingLayout->canvas_w ?? 1000, 'height' => $seatingLayout->canvas_h ?? 800];
                $sectionPositions = [];
                foreach ($seatingLayout->sections as $s) {
                    $sectionPositions[$s->name] = [
                        'x' => (int) $s->x_position, 'y' => (int) $s->y_position,
                        'width' => (int) $s->width, 'height' => (int) $s->height,
                        'rotation' => (float) ($s->rotation ?? 0),
                        'metadata' => $s->metadata ?? [],
                    ];
                }
                foreach ($sections as &$sec) {
                    $pos = $sectionPositions[$sec['name']] ?? null;
                    if ($pos) {
                        $sec['x'] = $pos['x'];
                        $sec['y'] = $pos['y'];
                        $sec['width'] = $pos['width'];
                        $sec['height'] = $pos['height'];
                        $sec['rotation'] = $pos['rotation'];
                        $sec['metadata'] = $pos['metadata'];
                    }
                }
                unset($sec);
            }
        }

        // Get event seat statuses (1 query)
        $eventSeats = \App\Models\Seating\EventSeat::where('event_seating_id', $layout->id)
            ->get()
            ->keyBy('seat_uid');

        // Get ticket types with seating assignments (1 query + 2 eager loads)
        $ticketTypes = TicketType::where('event_id', $eventModel->id)
            ->whereIn('status', ['active', 'on_sale', 'published'])
            ->with(['seatingRows', 'seatingSections'])
            ->get();

        // Build lookup maps for ticket type assignment
        $rowIdToTT = [];
        $sectionNameToTT = [];
        $sectionNameToId = [];

        // Load section/row IDs for mapping
        $seatingLayout = $seatingLayout ?? \App\Models\Seating\SeatingLayout::with(['sections.rows'])->find($eventModel->seating_layout_id);
        if ($seatingLayout) {
            $rowIdMap = []; // "sectionName|rowLabel" => row_id
            foreach ($seatingLayout->sections as $s) {
                $sectionNameToId[$s->name] = $s->id;
                foreach ($s->rows as $r) {
                    $rowIdMap[$s->name . '|' . $r->label] = $r->id;
                }
            }
        }

        foreach ($ticketTypes as $tt) {
            foreach ($tt->seatingRows as $row) {
                $rowIdToTT[$row->id] = $tt;
            }
            foreach ($tt->seatingSections as $section) {
                $sectionNameToTT[$section->name] = $tt;
            }
        }

        // Merge status + ticket type info directly into geometry seats
        foreach ($sections as &$sec) {
            foreach ($sec['rows'] as &$row) {
                foreach ($row['seats'] as &$seat) {
                    $uid = $seat['seat_uid'];
                    $eventSeat = $eventSeats->get($uid);
                    $seat['status'] = $eventSeat->status ?? 'available';

                    // Resolve ticket type: row-level first, then section-level
                    $tt = null;
                    $rowKey = $sec['name'] . '|' . $row['label'];
                    if (isset($rowIdMap[$rowKey]) && isset($rowIdToTT[$rowIdMap[$rowKey]])) {
                        $tt = $rowIdToTT[$rowIdMap[$rowKey]];
                    }
                    if (!$tt && isset($sectionNameToId[$sec['name']])) {
                        $secId = $sectionNameToId[$sec['name']];
                        // Check seatingSections pivot
                        foreach ($ticketTypes as $candidate) {
                            if ($candidate->seatingSections->contains('id', $secId)) {
                                $tt = $candidate;
                                break;
                            }
                        }
                    }

                    $seat['ticket_type_id'] = $tt?->id;
                    $seat['ticket_type_name'] = $tt?->name;
                    $seat['price'] = $tt ? (float) $tt->display_price : 0;
                    $seat['color'] = $tt?->color ?? '#8B5CF6';
                }
            }
        }
        unset($sec, $row, $seat);

        // Format ticket types for legend (with is_entry_ticket)
        $ticketTypesData = $ticketTypes->map(fn($tt) => [
            'id' => $tt->id,
            'name' => $tt->name,
            'price' => (float) $tt->display_price,
            'color' => $tt->color ?? '#8B5CF6',
            'currency' => $tt->currency ?? 'RON',
            'is_entry_ticket' => (bool) $tt->is_entry_ticket,
        ]);

        return $this->success([
            'event_seating_id' => $layout->id,
            'canvas' => $canvas,
            'sections' => $sections,
            'ticket_types' => $ticketTypesData,
        ]);
    }

    /**
     * Format event for list
     */
    protected function formatEvent(Event $event): array
    {
        // Get localized venue name (venue.name is a translatable JSON field)
        $venueName = null;
        if ($event->venue) {
            $venueName = $event->venue->getTranslation('name') ?? null;
        }

        // Calculate days until event
        $daysUntil = null;
        $eventDate = $this->getStartsAt($event);
        if ($eventDate) {
            $eventDateTime = Carbon::parse($eventDate);
            $daysUntil = (int) now()->startOfDay()->diffInDays($eventDateTime->startOfDay(), false);
        }

        return [
            'id' => $event->id,
            'name' => $this->getLocalizedTitle($event),
            'slug' => $event->slug,
            'starts_at' => $this->getStartsAt($event),
            'ends_at' => $this->getEndsAt($event),
            'venue_id' => $event->venue_id,
            'venue_name' => $venueName,
            'venue_address' => $event->venue?->address ?? $event->address ?? null,
            'venue_city' => $event->venue?->city ?? $event->marketplaceCity?->name ?? null,
            'category' => $event->marketplaceEventCategory?->name ?? null,
            'status' => $this->getEventStatus($event),
            'image' => $this->getStorageUrl($event->poster_url),
            'tickets_sold' => $event->total_tickets_sold,
            'revenue' => (float) $event->total_revenue,
            'views' => $event->views_count ?? 0,
            'days_until' => $daysUntil,
            'price_range' => $this->getPriceRange($event),
            'is_cancelled' => (bool) $event->is_cancelled,
            'is_postponed' => (bool) $event->is_postponed,
            'is_sold_out' => (bool) ($event->is_sold_out ?? false),
            'is_door_sales_only' => (bool) ($event->is_door_sales_only ?? false),
            'is_past' => $this->isEventPast($event),
            'is_editable' => $this->isEventEditable($event),
            'commission_rate' => $event->commission_rate !== null ? (float) $event->commission_rate : null,
            'use_fixed_commission' => (bool) $event->use_fixed_commission,
            'effective_commission_rate' => (float) $event->getEffectiveCommissionRate(),
            'commission_mode' => $event->getEffectiveCommissionMode(),
            'has_seating' => (bool) $event->seating_layout_id,
        ];
    }

    /**
     * Convert storage path to full URL
     */
    protected function getStorageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        // If it's already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Get the core storage base URL
        // Uses APP_URL/storage as the base, which should be the core server URL
        $baseUrl = rtrim(config('app.url'), '/') . '/storage';

        // Clean the path - remove leading slashes and 'storage/' prefix if present
        $cleanPath = ltrim($path, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        return $baseUrl . '/' . $cleanPath;
    }

    /**
     * Format event with details
     */
    protected function formatEventDetailed(Event $event): array
    {
        // Format genres if loaded
        $genres = [];
        $genreIds = [];
        if ($event->relationLoaded('eventGenres')) {
            $genres = $event->eventGenres->map(function ($genre) {
                $name = is_array($genre->name) ? ($genre->name['ro'] ?? $genre->name['en'] ?? array_values($genre->name)[0] ?? '') : $genre->name;
                return [
                    'id' => $genre->id,
                    'name' => $name,
                    'slug' => $genre->slug,
                ];
            })->toArray();
            $genreIds = $event->eventGenres->pluck('id')->toArray();
        }

        // Format artists if loaded
        $artists = [];
        $artistIds = [];
        if ($event->relationLoaded('artists')) {
            $artists = $event->artists->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'slug' => $artist->slug,
                    'image' => $artist->main_image_url,
                ];
            })->toArray();
            $artistIds = $event->artists->pluck('id')->toArray();
        }

        // Get localized venue name (venue.name is a translatable JSON field)
        $venueName = null;
        if ($event->venue) {
            $venueName = $event->venue->getTranslation('name') ?? null;
        }

        return [
            'id' => $event->id,
            'name' => $this->getLocalizedTitle($event),
            'slug' => $event->slug,
            'description' => $this->getLocalizedField($event, 'description'),
            'ticket_terms' => $this->getLocalizedField($event, 'ticket_terms'),
            'short_description' => $this->getLocalizedField($event, 'short_description'),
            'starts_at' => $this->getStartsAt($event),
            'ends_at' => $this->getEndsAt($event),
            'doors_open_at' => $this->getDoorsAt($event),
            'venue_id' => $event->venue_id,
            'venue_name' => $venueName,
            'venue_address' => $event->venue?->address ?? $event->address,
            'venue_city' => $event->venue?->city ?? $event->marketplaceCity?->name ?? null,
            'marketplace_event_category_id' => $event->marketplace_event_category_id,
            'category' => $event->marketplaceEventCategory?->name ?? null,
            'genre_ids' => $genreIds,
            'genres' => $genres,
            'artist_ids' => $artistIds,
            'artists' => $artists,
            'tags' => null,
            'image' => $this->getStorageUrl($event->poster_url),
            'cover_image' => $this->getStorageUrl($event->hero_image_url),
            'gallery' => null,
            'status' => $this->getEventStatus($event),
            'is_public' => $event->is_published,
            'is_featured' => $event->is_featured,
            'is_sold_out' => (bool) ($event->is_sold_out ?? false),
            'door_sales_only' => (bool) ($event->door_sales_only ?? false),
            'is_cancelled' => (bool) ($event->is_cancelled ?? false),
            'cancel_reason' => $event->cancel_reason,
            'is_postponed' => (bool) ($event->is_postponed ?? false),
            'postponed_date' => $event->postponed_date?->format('Y-m-d'),
            'postponed_start_time' => $event->postponed_start_time,
            'postponed_door_time' => $event->postponed_door_time,
            'postponed_end_time' => $event->postponed_end_time,
            'postponed_reason' => $event->postponed_reason,
            'capacity' => $event->total_capacity,
            'max_tickets_per_order' => 10,
            'sales_start_at' => null,
            'sales_end_at' => null,
            'rejection_reason' => null,
            'tickets_sold' => $event->total_tickets_sold,
            'revenue' => (float) $event->total_revenue,
            'views' => $event->views_count ?? 0,
            'ticket_types' => $event->ticketTypes->map(function ($tt) use ($event) {
                // Count valid/used tickets (excludes cancelled/refunded)
                $validTickets = \App\Models\Ticket::where('ticket_type_id', $tt->id)
                    ->whereHas('order', function ($q) use ($event) {
                        $q->where('event_id', $event->id)
                          ->whereIn('status', ['paid', 'confirmed', 'completed']);
                    })
                    ->whereIn('status', ['valid', 'used'])
                    ->count();

                // Count checked-in tickets
                $checkedIn = \App\Models\Ticket::where('ticket_type_id', $tt->id)
                    ->whereHas('order', function ($q) use ($event) {
                        $q->where('event_id', $event->id)
                          ->whereIn('status', ['paid', 'confirmed', 'completed']);
                    })
                    ->whereNotNull('checked_in_at')
                    ->count();

                // Available = total capacity minus valid sold tickets
                $available = $tt->quota_total ? max(0, $tt->quota_total - $validTickets) : 0;

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $tt->display_price,
                    'currency' => $tt->currency ?? 'RON',
                    'quantity' => $tt->quota_total,
                    'quantity_sold' => $validTickets,
                    'available' => $available,
                    'min_per_order' => 1,
                    'max_per_order' => 10,
                    'status' => $tt->status === 'active' ? 'on_sale' : $tt->status,
                    'is_visible' => $tt->status === 'active',
                    'is_entry_ticket' => (bool) ($tt->is_entry_ticket ?? false),
                    'has_seats' => (bool) $tt->hasSeatingAssigned(),
                    'color' => $tt->color ?? null,
                    'checked_in' => $checkedIn,
                ];
            }),
            'has_seating' => (bool) $event->seating_layout_id,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * Mask a name for privacy - show only first and last letter of each word
     * Example: "John Doe" -> "J**n D*e"
     */
    private function maskName(string $name): string
    {
        if (empty($name) || $name === 'Unknown') {
            return $name;
        }

        $words = explode(' ', $name);
        $maskedWords = [];

        foreach ($words as $word) {
            $length = mb_strlen($word);
            if ($length <= 2) {
                $maskedWords[] = str_repeat('*', $length);
            } elseif ($length === 3) {
                $maskedWords[] = mb_substr($word, 0, 1) . '*' . mb_substr($word, -1);
            } else {
                $maskedWords[] = mb_substr($word, 0, 1) . str_repeat('*', $length - 2) . mb_substr($word, -1);
            }
        }

        return implode(' ', $maskedWords);
    }

    /**
     * Mask an email for privacy - blur local part and domain
     * Example: "john@example.com" -> "j***@e*****.com"
     */
    private function maskEmail(string $email): string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return $email ? str_repeat('*', mb_strlen($email)) : '';
        }

        $parts = explode('@', $email);
        $local = $parts[0];
        $domain = $parts[1];

        // Mask local part
        $localLength = mb_strlen($local);
        if ($localLength <= 1) {
            $maskedLocal = '*';
        } elseif ($localLength <= 3) {
            $maskedLocal = mb_substr($local, 0, 1) . str_repeat('*', $localLength - 1);
        } else {
            $maskedLocal = mb_substr($local, 0, 1) . str_repeat('*', 3);
        }

        // Mask domain (before TLD)
        $domainParts = explode('.', $domain);
        if (count($domainParts) >= 2) {
            $domainName = $domainParts[0];
            $tld = implode('.', array_slice($domainParts, 1));
            $domainNameLength = mb_strlen($domainName);
            if ($domainNameLength <= 1) {
                $maskedDomain = '*';
            } else {
                $maskedDomain = mb_substr($domainName, 0, 1) . str_repeat('*', min(5, $domainNameLength - 1));
            }
            $maskedDomainFull = $maskedDomain . '.' . $tld;
        } else {
            $maskedDomainFull = str_repeat('*', mb_strlen($domain));
        }

        return $maskedLocal . '@' . $maskedDomainFull;
    }

    /**
     * Ensure the venue_gates table exists, auto-create if migration not yet run
     */
    private function ensureVenueGatesTable(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('venue_gates')) {
            \Illuminate\Support\Facades\Schema::create('venue_gates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type')->default('entry');
                $table->string('location')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['venue_id', 'type']);
            });
        }
    }

    /**
     * List gates for a venue
     */
    public function venueGates(Request $request, int $venueId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Try with marketplace_client_id first, then fallback to just venue id
        $venue = \App\Models\Venue::where('id', $venueId)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$venue) {
            $venue = \App\Models\Venue::find($venueId);
        }

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $this->ensureVenueGatesTable();
        $gates = $venue->gates()->get();

        return $this->success([
            'venue' => [
                'id' => $venue->id,
                'name' => $venue->getTranslation('name', 'ro') ?? $venue->name,
                'address' => $venue->address,
                'city' => $venue->city,
            ],
            'gates' => $gates->map(fn($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'location' => $g->location,
                'is_active' => $g->is_active,
                'sort_order' => $g->sort_order,
            ]),
        ]);
    }

    /**
     * Create a gate for a venue
     */
    public function createVenueGate(Request $request, int $venueId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Try with marketplace_client_id first, then fallback to just venue id
        $venue = \App\Models\Venue::where('id', $venueId)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$venue) {
            $venue = \App\Models\Venue::find($venueId);
        }

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:entry,vip,pos,exit',
            'location' => 'nullable|string|max:255',
        ]);

        $this->ensureVenueGatesTable();

        $maxSort = $venue->gates()->max('sort_order') ?? 0;

        $gate = \App\Models\VenueGate::create([
            'venue_id' => $venue->id,
            'name' => $request->name,
            'type' => $request->type,
            'location' => $request->location,
            'is_active' => true,
            'sort_order' => $maxSort + 1,
        ]);

        return $this->success([
            'gate' => [
                'id' => $gate->id,
                'name' => $gate->name,
                'type' => $gate->type,
                'location' => $gate->location,
                'is_active' => $gate->is_active,
                'sort_order' => $gate->sort_order,
            ],
        ], 'Gate created', 201);
    }

    /**
     * Update a gate
     */
    public function updateVenueGate(Request $request, int $venueId, int $gateId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $venue = \App\Models\Venue::where('id', $venueId)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$venue) {
            $venue = \App\Models\Venue::find($venueId);
        }

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $gate = $venue->gates()->where('id', $gateId)->first();
        if (!$gate) {
            return $this->error('Gate not found', 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:entry,vip,pos,exit',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $gate->update($request->only(['name', 'type', 'location', 'is_active']));

        return $this->success([
            'gate' => [
                'id' => $gate->id,
                'name' => $gate->name,
                'type' => $gate->type,
                'location' => $gate->location,
                'is_active' => $gate->is_active,
                'sort_order' => $gate->sort_order,
            ],
        ]);
    }

    /**
     * Delete a gate
     */
    public function deleteVenueGate(Request $request, int $venueId, int $gateId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $venue = \App\Models\Venue::where('id', $venueId)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$venue) {
            $venue = \App\Models\Venue::find($venueId);
        }

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $gate = $venue->gates()->where('id', $gateId)->first();
        if (!$gate) {
            return $this->error('Gate not found', 404);
        }

        $gate->delete();

        return $this->success(null, 'Gate deleted');
    }

    /**
     * Resolve any private venue-owner notes attached to a ticket's context
     * (ticket / order / customer). Venue-type tenants can leave notes that are
     * intentionally visible to marketplace scan users (per product spec).
     *
     * Returns an empty array when the event's venue is not owned by a tenant,
     * when no notes exist, or on unexpected errors.
     */
    protected function resolveVenueNotesForTicket(\App\Models\Ticket $ticket): array
    {
        try {
            $eventId = $ticket->event_id;
            if (!$eventId) return [];

            $event = Event::with('venue:id,tenant_id')->find($eventId);
            $venueTenantId = $event?->venue?->tenant_id;
            if (!$venueTenantId) return [];

            return VenueOwnerNote::forTicketContext((int) $venueTenantId, $ticket)
                ->map(function (VenueOwnerNote $n) {
                    $a = $n->author;
                    return [
                        'id' => (string) $n->id,
                        'target_type' => $n->target_type,
                        'note' => $n->note,
                        'created_at' => $n->created_at?->toIso8601String(),
                        'author' => $a ? [
                            'name' => $a->name,
                        ] : null,
                    ];
                })->values()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
