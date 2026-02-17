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
use App\Notifications\MarketplaceOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

            $eventDate = $startsAt ? Carbon::parse($startsAt)->toDateString() : null;
            $startTime = $startsAt ? Carbon::parse($startsAt)->format('H:i') : null;
            $endTime = $endsAt ? Carbon::parse($endsAt)->format('H:i') : null;
            $doorTime = $doorsAt ? Carbon::parse($doorsAt)->format('H:i') : null;

            $event = Event::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'title' => ['ro' => $validated['name'], 'en' => $validated['name']],
                'slug' => $slug,
                'description' => ['ro' => $validated['description'] ?? '', 'en' => $validated['description'] ?? ''],
                'ticket_terms' => ['ro' => $validated['ticket_terms'] ?? '', 'en' => $validated['ticket_terms'] ?? ''],
                'short_description' => ['ro' => $validated['short_description'] ?? '', 'en' => $validated['short_description'] ?? ''],
                'duration_mode' => 'single_day',
                'event_date' => $eventDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'door_time' => $doorTime,
                'venue_id' => $validated['venue_id'] ?? null,
                'address' => $validated['venue_address'] ?? null,
                'marketplace_event_category_id' => $validated['marketplace_event_category_id'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'facebook_url' => $validated['facebook_url'] ?? null,
                'is_published' => false,
            ]);

            // Create ticket types using TicketType model
            foreach ($validated['ticket_types'] ?? [] as $index => $ticketTypeData) {
                TicketType::create([
                    'event_id' => $event->id,
                    'name' => $ticketTypeData['name'],
                    'description' => $ticketTypeData['description'] ?? null,
                    'price_cents' => (int) ($ticketTypeData['price'] * 100),
                    'currency' => 'RON',
                    'quota_total' => $ticketTypeData['quantity'] ?? 0,
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
            if (isset($validated['starts_at'])) {
                $startsAt = Carbon::parse($validated['starts_at']);
                $updateData['event_date'] = $startsAt->toDateString();
                $updateData['start_time'] = $startsAt->format('H:i');
            }
            if (isset($validated['ends_at'])) {
                $endsAt = Carbon::parse($validated['ends_at']);
                $updateData['end_time'] = $endsAt->format('H:i');
            }
            if (isset($validated['doors_open_at'])) {
                $doorsAt = Carbon::parse($validated['doors_open_at']);
                $updateData['door_time'] = $doorsAt->format('H:i');
            }
            if (isset($validated['venue_id'])) {
                $updateData['venue_id'] = $validated['venue_id'];
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
                        'quota_total' => $ticketTypeData['quantity'] ?? 0,
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
            return $this->error('Event is already published', 400);
        }

        // Validate event has required data
        if (!$event->ticketTypes()->exists()) {
            return $this->error('Event must have at least one ticket type', 400);
        }

        $event->update(['is_published' => true]);

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load(['ticketTypes', 'venue'])),
        ], 'Event published successfully');
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

                // Send email notification to customer
                if ($order->customer_email) {
                    dispatch(function () use ($order) {
                        Notification::route('mail', $order->customer_email)
                            ->notify(new MarketplaceOrderNotification($order->fresh(), 'event_cancelled'));
                    })->afterResponse();
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

        // Include orders with paid/confirmed/completed status (all represent valid tickets)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        $query = \App\Models\Ticket::whereHas('order', function ($q) use ($event, $validOrderStatuses) {
                $q->where('event_id', $event->id)
                    ->whereIn('status', $validOrderStatuses);
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

        // Get stats
        $totalTickets = \App\Models\Ticket::whereHas('order', function ($q) use ($event, $validOrderStatuses) {
            $q->where('event_id', $event->id)->whereIn('status', $validOrderStatuses);
        })->count();

        $checkedInCount = \App\Models\Ticket::whereHas('order', function ($q) use ($event, $validOrderStatuses) {
            $q->where('event_id', $event->id)->whereIn('status', $validOrderStatuses);
        })->whereNotNull('checked_in_at')->count();

        return $this->paginated($tickets, function ($ticket) {
            $customer = $ticket->order->marketplaceCustomer;
            $ticketType = $ticket->ticketType;
            return [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
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
                'order_number' => $ticket->order->order_number,
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

        // Include orders with paid/confirmed/completed status (all represent valid tickets)
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

        $statsQuery = \App\Models\Ticket::whereHas('order', function ($q) use ($statsEventIds, $validOrderStatuses) {
            $q->whereIn('event_id', $statsEventIds)->whereIn('status', $validOrderStatuses);
        });

        $totalTickets = $statsQuery->count();
        $checkedInCount = (clone $statsQuery)->whereNotNull('checked_in_at')->count();

        // Calculate revenue for the selected event(s)
        $revenue = Order::whereIn('event_id', $statsEventIds)
            ->whereIn('status', $validOrderStatuses)
            ->sum('total');

        // Get unique orders count
        $ordersCount = Order::whereIn('event_id', $statsEventIds)
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

        // Include orders with paid/confirmed/completed status (all represent valid tickets)
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

        if (!$ticket) {
            return $this->error('Ticket not found or invalid', 404);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

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

        // Include orders with paid/confirmed/completed status (all represent valid tickets)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        $ticket = \App\Models\Ticket::where('barcode', $barcode)
            ->whereHas('order', function ($q) use ($event, $validOrderStatuses) {
                $q->where('event_id', $event->id)
                    ->whereIn('status', $validOrderStatuses);
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found or invalid', 404);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

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

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
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
                'Customer Email',
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
                    $customer?->email ?? $ticket->order->customer_email,
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
                'Customer Email' => $customer?->email ?? $ticket->order->customer_email ?? '',
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

        // Valid order statuses for analytics
        $validStatuses = ['paid', 'confirmed', 'completed'];

        // Base query for orders in the range
        $ordersQuery = $event->orders()
            ->whereIn('status', $validStatuses)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        // Overview metrics
        $totalRevenue = (float) $ordersQuery->sum('total');
        $ticketsSold = (int) $ordersQuery->withCount('tickets')->get()->sum('tickets_count');
        $pageViews = $event->views_count ?? 0;
        $conversionRate = $pageViews > 0 ? round(($ticketsSold / $pageViews) * 100, 2) : 0;

        // Previous period for comparison
        $periodDays = $rangeStart->diffInDays($rangeEnd);
        $prevStart = $rangeStart->copy()->subDays($periodDays);
        $prevEnd = $rangeStart->copy()->subSecond();

        $prevOrdersQuery = $event->orders()
            ->whereIn('status', $validStatuses)
            ->whereBetween('created_at', [$prevStart, $prevEnd]);
        $prevRevenue = (float) $prevOrdersQuery->sum('total');
        $prevTickets = (int) $prevOrdersQuery->withCount('tickets')->get()->sum('tickets_count');

        $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
        $ticketsChange = $prevTickets > 0 ? round((($ticketsSold - $prevTickets) / $prevTickets) * 100, 1) : 0;

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

        while ($currentDate <= $rangeEnd) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            $dayOrders = $event->orders()
                ->whereIn('status', $validStatuses)
                ->whereBetween('created_at', [$dayStart, $dayEnd]);

            $dailyData[] = [
                'label' => $currentDate->format('M d'),
                'date' => $currentDate->format('Y-m-d'),
                'revenue' => (float) $dayOrders->sum('total'),
                'tickets' => (int) $dayOrders->withCount('tickets')->get()->sum('tickets_count'),
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

        // Traffic sources (simplified - would need proper tracking implementation)
        $trafficSources = [
            ['source' => 'Direct', 'visitors' => max(1, intval($pageViews * 0.4)), 'revenue' => round($totalRevenue * 0.4, 2)],
            ['source' => 'Facebook', 'visitors' => intval($pageViews * 0.25), 'revenue' => round($totalRevenue * 0.25, 2)],
            ['source' => 'Google', 'visitors' => intval($pageViews * 0.2), 'revenue' => round($totalRevenue * 0.2, 2)],
            ['source' => 'Instagram', 'visitors' => intval($pageViews * 0.1), 'revenue' => round($totalRevenue * 0.1, 2)],
            ['source' => 'Other', 'visitors' => intval($pageViews * 0.05), 'revenue' => round($totalRevenue * 0.05, 2)],
        ];

        // Ticket performance
        $ticketPerformance = $event->ticketTypes->map(function ($tt) use ($event, $validStatuses) {
            $sold = $event->orders()
                ->whereIn('status', $validStatuses)
                ->whereHas('tickets', fn ($q) => $q->where('ticket_type_id', $tt->id))
                ->withCount(['tickets' => fn ($q) => $q->where('ticket_type_id', $tt->id)])
                ->get()
                ->sum('tickets_count');

            return [
                'name' => $tt->name,
                'price' => $tt->display_price,
                'sold' => $sold,
                'capacity' => $tt->quota_total,
            ];
        });

        // Top locations - empty until real location tracking is implemented
        // Real data would come from a visitor_logs or analytics table with geolocation
        $topLocations = [];

        // Recent sales
        $recentSales = $event->orders()
            ->whereIn('status', $validStatuses)
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
                'total_revenue' => $totalRevenue,
                'tickets_sold' => $ticketsSold,
                'page_views' => $pageViews,
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
        if ($event->event_date) {
            $date = $event->event_date->format('Y-m-d');
            $time = $event->start_time ?? '00:00';
            return Carbon::parse("{$date} {$time}")->toIso8601String();
        }
        return null;
    }

    /**
     * Get ends_at datetime from Event model
     */
    protected function getEndsAt(Event $event): ?string
    {
        if ($event->event_date && $event->end_time) {
            $date = $event->event_date->format('Y-m-d');
            return Carbon::parse("{$date} {$event->end_time}")->toIso8601String();
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
            'ticket_types' => $event->ticketTypes->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $tt->display_price,
                    'currency' => $tt->currency ?? 'RON',
                    'quantity' => $tt->quota_total,
                    'quantity_sold' => $tt->quota_sold,
                    'available' => $tt->available_quantity,
                    'min_per_order' => 1,
                    'max_per_order' => 10,
                    'status' => $tt->status === 'active' ? 'on_sale' : $tt->status,
                    'is_visible' => $tt->status === 'active',
                ];
            }),
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
     * List gates for a venue
     */
    public function venueGates(Request $request, int $venueId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $venue = \App\Models\Venue::where('id', $venueId)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

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

        $venue = \App\Models\Venue::where('id', $venueId)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:entry,vip,pos,exit',
            'location' => 'nullable|string|max:255',
        ]);

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
            return $this->error('Venue not found', 404);
        }

        $gate = $venue->gates()->where('id', $gateId)->first();
        if (!$gate) {
            return $this->error('Gate not found', 404);
        }

        $gate->delete();

        return $this->success(null, 'Gate deleted');
    }
}
