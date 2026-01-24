<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTicketType;
use App\Models\MarketplaceTransaction;
use App\Models\Order;
use App\Notifications\MarketplaceOrderNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class EventsController extends BaseController
{
    /**
     * List organizer's events
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = MarketplaceEvent::where('marketplace_organizer_id', $organizer->id)
            ->with('ticketTypes');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('upcoming')) {
            $query->where('starts_at', '>=', now());
        }

        if ($request->has('past')) {
            $query->where('starts_at', '<', now());
        }

        // Sorting
        $sortField = $request->get('sort', 'starts_at');
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

        $event = MarketplaceEvent::with(['ticketTypes', 'venue'])
            ->where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
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
            while (MarketplaceEvent::where('marketplace_client_id', $organizer->marketplace_client_id)
                ->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $event = MarketplaceEvent::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'ticket_terms' => $validated['ticket_terms'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'doors_open_at' => $validated['doors_open_at'] ?? null,
                'venue_id' => $validated['venue_id'] ?? null,
                'venue_name' => $validated['venue_name'] ?? null,
                'venue_address' => $validated['venue_address'] ?? null,
                'venue_city' => $validated['venue_city'] ?? null,
                'marketplace_event_category_id' => $validated['marketplace_event_category_id'] ?? null,
                'genre_ids' => $validated['genre_ids'] ?? null,
                'artist_ids' => $validated['artist_ids'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'facebook_url' => $validated['facebook_url'] ?? null,
                'category' => $validated['category'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'max_tickets_per_order' => $validated['max_tickets_per_order'] ?? 10,
                'sales_start_at' => $validated['sales_start_at'] ?? null,
                'sales_end_at' => $validated['sales_end_at'] ?? null,
                'status' => 'draft',
            ]);

            // Create ticket types
            foreach ($validated['ticket_types'] ?? [] as $index => $ticketTypeData) {
                MarketplaceTicketType::create([
                    'marketplace_event_id' => $event->id,
                    'name' => $ticketTypeData['name'],
                    'description' => $ticketTypeData['description'] ?? null,
                    'price' => $ticketTypeData['price'],
                    'currency' => 'RON',
                    'quantity' => $ticketTypeData['quantity'] ?? null,
                    'min_per_order' => $ticketTypeData['min_per_order'] ?? 1,
                    'max_per_order' => $ticketTypeData['max_per_order'] ?? 10,
                    'status' => 'on_sale',
                    'is_visible' => true,
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            return $this->success([
                'event' => $this->formatEventDetailed($event->load('ticketTypes')),
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

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Can only edit draft or rejected events fully
        // Published events have limited editing
        $isPublished = $event->isPublished();

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

        if (!$isPublished) {
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
            ]);
        }

        $validated = $request->validate($rules);

        $event->update($validated);

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load('ticketTypes')),
        ], 'Event updated');
    }

    /**
     * Submit event for review
     */
    public function submit(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if (!$event->isDraft()) {
            return $this->error('Only draft events can be submitted for review', 400);
        }

        // Validate event has required data
        if (!$event->ticketTypes()->exists()) {
            return $this->error('Event must have at least one ticket type', 400);
        }

        $event->submitForReview();

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load('ticketTypes')),
        ], 'Event submitted for review');
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

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->isCancelled()) {
            return $this->error('Event is already cancelled', 400);
        }

        $reason = $validated['reason'] ?? 'Event cancelled by organizer';

        try {
            DB::beginTransaction();

            // Get all completed orders for this event
            $completedOrders = $event->orders()->where('status', 'completed')->get();
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
                            ->increment('available_quantity', $item->quantity);
                    }
                }
            }

            // Cancel the event
            $event->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
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
     * Get event participants (ticket holders)
     */
    public function participants(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $query = \App\Models\Ticket::whereHas('order', function ($q) use ($event) {
                $q->where('marketplace_event_id', $event->id)
                    ->where('status', 'completed');
            })
            ->with(['order.marketplaceCustomer', 'marketplaceTicketType']);

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
            $query->where('marketplace_ticket_type_id', $request->ticket_type_id);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 50), 200);
        $tickets = $query->paginate($perPage);

        // Get stats
        $totalTickets = \App\Models\Ticket::whereHas('order', function ($q) use ($event) {
            $q->where('marketplace_event_id', $event->id)->where('status', 'completed');
        })->count();

        $checkedInCount = \App\Models\Ticket::whereHas('order', function ($q) use ($event) {
            $q->where('marketplace_event_id', $event->id)->where('status', 'completed');
        })->whereNotNull('checked_in_at')->count();

        return $this->paginated($tickets, function ($ticket) {
            $customer = $ticket->order->marketplaceCustomer;
            return [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'qr_code' => $ticket->qr_code_url ?? null,
                'ticket_type' => $ticket->marketplaceTicketType?->name,
                'ticket_type_id' => $ticket->marketplace_ticket_type_id,
                'price' => (float) ($ticket->marketplaceTicketType?->price ?? 0),
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
     * Check in a ticket
     */
    public function checkIn(Request $request, int $eventId, string $barcode): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $ticket = \App\Models\Ticket::where('barcode', $barcode)
            ->whereHas('order', function ($q) use ($event) {
                $q->where('marketplace_event_id', $event->id)
                    ->where('status', 'completed');
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found or invalid', 404);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        if ($ticket->checked_in_at) {
            return $this->error('Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'), 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $organizer->contact_name ?? $organizer->name,
        ]);

        $customer = $ticket->order->marketplaceCustomer;

        return $this->success([
            'ticket' => [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->marketplaceTicketType?->name,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at->toIso8601String(),
            ],
            'customer' => [
                'name' => $customer
                    ? $customer->first_name . ' ' . $customer->last_name
                    : $ticket->order->customer_name,
                'email' => $customer?->email ?? $ticket->order->customer_email,
            ],
        ], 'Ticket checked in successfully');
    }

    /**
     * Undo check-in for a ticket
     */
    public function undoCheckIn(Request $request, int $eventId, string $barcode): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $ticket = \App\Models\Ticket::where('barcode', $barcode)
            ->whereHas('order', function ($q) use ($event) {
                $q->where('marketplace_event_id', $event->id)
                    ->where('status', 'completed');
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

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            abort(404, 'Event not found');
        }

        $tickets = \App\Models\Ticket::whereHas('order', function ($q) use ($event) {
                $q->where('marketplace_event_id', $event->id)
                    ->where('status', 'completed');
            })
            ->with(['order.marketplaceCustomer', 'marketplaceTicketType'])
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
                fputcsv($handle, [
                    $ticket->id,
                    $ticket->barcode,
                    $ticket->marketplaceTicketType?->name ?? 'N/A',
                    $ticket->marketplaceTicketType?->price ?? 0,
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
     * Get event statistics
     */
    public function statistics(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $completedOrders = $event->orders()->where('status', 'completed');

        $ticketStats = $event->ticketTypes()->get()->map(function ($tt) use ($event) {
            $sold = $event->orders()
                ->where('status', 'completed')
                ->whereHas('tickets', function ($q) use ($tt) {
                    $q->where('marketplace_ticket_type_id', $tt->id);
                })
                ->withCount(['tickets' => function ($q) use ($tt) {
                    $q->where('marketplace_ticket_type_id', $tt->id);
                }])
                ->get()
                ->sum('tickets_count');

            return [
                'id' => $tt->id,
                'name' => $tt->name,
                'price' => $tt->price,
                'quantity' => $tt->quantity,
                'sold' => $sold,
                'available' => $tt->available_quantity,
                'revenue' => $sold * $tt->price,
            ];
        });

        return $this->success([
            'event_id' => $event->id,
            'event_name' => $event->name,
            'summary' => [
                'total_orders' => $completedOrders->count(),
                'total_tickets_sold' => $event->tickets_sold,
                'gross_revenue' => (float) $event->revenue,
                'commission_rate' => $organizer->getEffectiveCommissionRate(),
                'commission_amount' => round($event->revenue * $organizer->getEffectiveCommissionRate() / 100, 2),
                'net_revenue' => round($event->revenue * (1 - $organizer->getEffectiveCommissionRate() / 100), 2),
                'views' => $event->views,
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
     * Format event for list
     */
    protected function formatEvent(MarketplaceEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'starts_at' => $event->starts_at->toIso8601String(),
            'venue_name' => $event->venue_display_name,
            'venue_city' => $event->venue_display_city,
            'category' => $event->category,
            'status' => $event->status,
            'image' => $event->image_url,
            'tickets_sold' => $event->tickets_sold,
            'revenue' => (float) $event->revenue,
            'views' => $event->views,
            'price_range' => $event->price_range,
        ];
    }

    /**
     * Format event with details
     */
    protected function formatEventDetailed(MarketplaceEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'description' => $event->description,
            'ticket_terms' => $event->ticket_terms,
            'short_description' => $event->short_description,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'doors_open_at' => $event->doors_open_at?->toIso8601String(),
            'venue_id' => $event->venue_id,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'venue_city' => $event->venue_city,
            'marketplace_event_category_id' => $event->marketplace_event_category_id,
            'category' => $event->category,
            'tags' => $event->tags,
            'image' => $event->image_url,
            'cover_image' => $event->cover_image_url,
            'gallery' => $event->gallery,
            'status' => $event->status,
            'is_public' => $event->is_public,
            'is_featured' => $event->is_featured,
            'capacity' => $event->capacity,
            'max_tickets_per_order' => $event->max_tickets_per_order,
            'sales_start_at' => $event->sales_start_at?->toIso8601String(),
            'sales_end_at' => $event->sales_end_at?->toIso8601String(),
            'rejection_reason' => $event->rejection_reason,
            'tickets_sold' => $event->tickets_sold,
            'revenue' => (float) $event->revenue,
            'views' => $event->views,
            'ticket_types' => $event->ticketTypes->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $tt->price,
                    'currency' => $tt->currency,
                    'quantity' => $tt->quantity,
                    'quantity_sold' => $tt->quantity_sold,
                    'available' => $tt->available_quantity,
                    'min_per_order' => $tt->min_per_order,
                    'max_per_order' => $tt->max_per_order,
                    'status' => $tt->status,
                    'is_visible' => $tt->is_visible,
                ];
            }),
            'created_at' => $event->created_at->toIso8601String(),
        ];
    }
}
