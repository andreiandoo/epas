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

        // Per-event whitelist: when the caller is a non-admin team member and
        // they have an explicit event whitelist, restrict the list. Empty
        // whitelist = no restriction (legacy default). Admins always see all.
        $member = $this->currentTeamMember($request);
        if ($member && $member->role !== 'admin') {
            $allowedIds = $member->events()->pluck('events.id')->all();
            if (!empty($allowedIds)) {
                $query->whereIn('id', $allowedIds);
            }
        }

        // Mobile-only flag: hide events that aren't approved yet (drafts,
        // pending review, rejected). The mobile scanner / POS app has nothing
        // operational to do with those, and they confused organizers.
        if ($request->boolean('published_only')) {
            $query->where('is_published', true)
                  ->where('is_cancelled', false);
        }

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
        $sortField = $request->input('sort', 'event_date');
        if ($sortField === 'starts_at') {
            $sortField = 'event_date';
        }
        $sortDir = $request->input('order', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->input('per_page', 20), 100);
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

        // Non-admin team members can only access events on their whitelist.
        $member = $this->currentTeamMember($request);
        if ($member && $member->role !== 'admin') {
            $allowedIds = $member->events()->pluck('events.id')->all();
            if (!empty($allowedIds) && !in_array((int) $event->id, $allowedIds, true)) {
                return $this->error('Event not accessible', 403);
            }
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
            'thank_you_message' => 'nullable|string|max:20000',
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
            'event_website_url' => 'nullable|url|max:500',
            'facebook_url' => 'nullable|url|max:500',
            'video_url' => 'nullable|url|max:500',
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
                'thank_you_message' => !empty($validated['thank_you_message'])
                    ? ['ro' => $validated['thank_you_message'], 'en' => $validated['thank_you_message']]
                    : null,
                'short_description' => ['ro' => $validated['short_description'] ?? '', 'en' => $validated['short_description'] ?? ''],
                'duration_mode' => $durationMode,
                'venue_id' => $validated['venue_id'] ?? null,
                'suggested_venue_name' => empty($validated['venue_id']) ? ($validated['venue_name'] ?? null) : null,
                'address' => $validated['venue_address'] ?? null,
                'marketplace_event_category_id' => $validated['marketplace_event_category_id'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'event_website_url' => $validated['event_website_url'] ?? null,
                'facebook_url' => $validated['facebook_url'] ?? null,
                'video_url' => $validated['video_url'] ?? null,
                'is_published' => false,
            ], $dateFields));

            // Create ticket types using TicketType model.
            // NB: price_cents / quota_total are real DB columns but NOT in
            // TicketType::$fillable — assignments via mass-create go nowhere.
            // Route through the virtual price_max / capacity attributes whose
            // mutators correctly write to those underlying columns.
            // min_per_order / max_per_order are NOT NULL in the DB; default
            // them to 1 and the event-level max_tickets_per_order (or 10) so
            // organizers don't have to fill those fields per ticket type.
            $defaultMaxPerOrder = (int) ($validated['max_tickets_per_order'] ?? 10);
            foreach ($validated['ticket_types'] ?? [] as $index => $ticketTypeData) {
                TicketType::create([
                    'event_id' => $event->id,
                    'name' => $ticketTypeData['name'],
                    'description' => $ticketTypeData['description'] ?? null,
                    'currency' => 'RON',
                    'price_max' => $ticketTypeData['price'],
                    'capacity' => $ticketTypeData['quantity'] ?? null, // null becomes -1 = unlimited
                    'min_per_order' => $ticketTypeData['min_per_order'] ?? 1,
                    'max_per_order' => $ticketTypeData['max_per_order'] ?? $defaultMaxPerOrder,
                    'quota_sold' => 0,
                    'status' => 'active',
                ]);
            }

            // Auto-fill eventTypes from the chosen category — this matches the
            // Filament admin behavior so Genuri eveniment isn't blank when the
            // operator opens the event in core admin.
            if (!empty($validated['marketplace_event_category_id'])) {
                $category = \App\Models\MarketplaceEventCategory::find($validated['marketplace_event_category_id']);
                if ($category && !empty($category->event_type_ids)) {
                    $event->eventTypes()->sync($category->event_type_ids);
                }
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
            'thank_you_message' => 'nullable|string|max:20000',
            'short_description' => 'nullable|string|max:500',
            'duration_mode' => 'nullable|string|in:single_day,range,multi_day',
            'doors_open_at' => 'nullable|date',
            'marketplace_event_category_id' => 'nullable|integer|exists:marketplace_event_categories,id',
            'genre_ids' => 'nullable|array',
            'artist_ids' => 'nullable|array',
            'website_url' => 'nullable|url|max:500',
            'event_website_url' => 'nullable|url|max:500',
            'facebook_url' => 'nullable|url|max:500',
            'video_url' => 'nullable|url|max:500',
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
            if (array_key_exists('thank_you_message', $validated)) {
                // Empty string / null clears the field so the thank-you page
                // renders without the message card. Non-empty gets sanitized
                // downstream by the Event model's setThankYouMessageAttribute
                // mutator via HTMLPurifier.
                $tym = $validated['thank_you_message'] ?? '';
                $updateData['thank_you_message'] = ($tym !== '' && $tym !== null)
                    ? ['ro' => $tym, 'en' => $tym]
                    : null;
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
            if (isset($validated['event_website_url'])) {
                $updateData['event_website_url'] = $validated['event_website_url'];
            }
            if (isset($validated['facebook_url'])) {
                $updateData['facebook_url'] = $validated['facebook_url'];
            }
            if (isset($validated['video_url'])) {
                $updateData['video_url'] = $validated['video_url'];
            }

            if (!empty($updateData)) {
                $event->update($updateData);
            }

            // Sync ticket types if provided (only for unpublished events - we block published above)
            if ($ticketTypesData !== null) {
                // Delete existing ticket types and recreate
                $event->ticketTypes()->delete();

                // Use virtual price_max / capacity (mass-assignable) so the
                // mutators populate price_cents / quota_total correctly.
                // Default min_per_order=1 and max_per_order=event.max_tickets_per_order
                // (or 10) so the NOT-NULL constraint is satisfied without making
                // those fields required in the form.
                $defaultMaxPerOrderUpdate = (int) ($validated['max_tickets_per_order'] ?? $event->max_tickets_per_order ?? 10);
                foreach ($ticketTypesData as $index => $ticketTypeData) {
                    TicketType::create([
                        'event_id' => $event->id,
                        'name' => $ticketTypeData['name'],
                        'description' => $ticketTypeData['description'] ?? null,
                        'currency' => 'RON',
                        'price_max' => $ticketTypeData['price'],
                        'capacity' => $ticketTypeData['quantity'] ?? null,
                        'min_per_order' => $ticketTypeData['min_per_order'] ?? 1,
                        'max_per_order' => $ticketTypeData['max_per_order'] ?? $defaultMaxPerOrderUpdate,
                        'quota_sold' => 0,
                        'status' => 'active',
                    ]);
                }
            }

            // Auto-fill eventTypes from the chosen category — keeps the Filament
            // admin's conditional Tipuri/Genuri chain consistent with what the
            // organizer selected.
            if (isset($validated['marketplace_event_category_id'])) {
                $category = \App\Models\MarketplaceEventCategory::find($validated['marketplace_event_category_id']);
                if ($category && !empty($category->event_type_ids)) {
                    $event->eventTypes()->sync($category->event_type_ids);
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

        // After a rejection, the organizer can edit and resubmit. Only block
        // re-submit when there's a pending review without a prior rejection.
        if ($event->submitted_at && !$event->rejected_at) {
            return $this->error('Evenimentul a fost deja trimis spre aprobare', 400);
        }

        // Validate event has required data
        if (!$event->ticketTypes()->exists()) {
            return $this->error('Event must have at least one ticket type', 400);
        }

        $event->update([
            'submitted_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load(['ticketTypes', 'venue'])),
        ], 'Evenimentul a fost trimis spre aprobare');
    }

    /**
     * Cancel an event. Does NOT issue any refunds — refunds are a manual
     * admin action via the per-order Rambursare flow (which actually calls
     * NETOPIA / Stripe). This endpoint only:
     *   - flips the event to is_cancelled
     *   - cancels pending (unpaid) orders to release ticket inventory
     *   - leaves all paid/confirmed/completed orders untouched so admins
     *     can review and refund (or not) one by one
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

            // Cancel pending (unpaid) orders to free up ticket inventory.
            // No refund needed — no money has changed hands for these.
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

            // Count paid orders that admin will need to review for refunds.
            $paidOrdersCount = $event->orders()
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->count();

            // Mark the event as cancelled.
            $event->update([
                'is_cancelled' => true,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // Update organizer stats
            $organizer->updateStats();

            DB::commit();

            $message = 'Eveniment anulat. ' . $pendingOrders->count() . ' comenzi neplătite au fost anulate.';
            if ($paidOrdersCount > 0) {
                $message .= ' ' . $paidOrdersCount . ' comenzi plătite necesită decizie manuală de rambursare din panoul de administrare.';
            }

            return $this->success([
                'event_id' => $event->id,
                'orders_cancelled' => $pendingOrders->count(),
                'paid_orders_pending_review' => $paidOrdersCount,
            ], $message);

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

        // Scope tickets via tickets.event_id — NOT order.event_id. Mixed-cart
        // orders contain items from multiple events but the order has only a
        // single event_id field, so the order's event_id can't be trusted to
        // attribute tickets correctly. Each ticket carries its own event_id
        // (set at creation) which is the source of truth. Event ownership for
        // this organizer was already verified above; the order's organizer/
        // marketplace fields are intentionally not re-checked here so a
        // mixed-cart order placed against a different "primary" event still
        // surfaces the items that actually belong to *this* event.
        $eventScope = function ($q) use ($event, $validOrderStatuses) {
            $q->where(function ($paid) use ($event, $validOrderStatuses) {
                $paid->where('event_id', $event->id)
                    ->whereHas('order', fn ($oq) => $oq->whereIn('status', $validOrderStatuses));
            })
            ->orWhere(function ($inv) use ($event) {
                // Invitations: no order. tickets.event_id is set at issue
                // time; ticketType.event_id is the secondary fallback for
                // any older invite rows that pre-date the event_id stamp.
                $inv->whereNull('order_id')
                    ->where(function ($ev) use ($event) {
                        $ev->where('event_id', $event->id)
                            ->orWhereHas('ticketType', fn ($ttq) => $ttq->where('event_id', $event->id));
                    });
            });
        };

        $query = \App\Models\Ticket::where($eventScope)
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

        $perPage = min((int) $request->input('per_page', 50), 200);
        $tickets = $query->paginate($perPage);

        // Get stats — count only valid/used tickets (excludes cancelled/refunded);
        // include invitations (no order) alongside regular tickets. Same
        // tickets.event_id-based scoping as the listing query.
        $statsBase = function () use ($eventScope) {
            return \App\Models\Ticket::where($eventScope);
        };

        $totalTickets = $statsBase()->whereIn('status', ['valid', 'used'])->count();
        $checkedInCount = $statsBase()->whereNotNull('checked_in_at')->count();

        // Online vs. la ușă split + per-source ticket-type breakdown.
        // Consumed by the mobile Tixello + PWA scanapp dashboards to
        // render the "Online vs. la ușă" stacked bar with click-to-
        // expand ticket-type detail underneath. Source semantics:
        //   'pos_app'  → sold at the door (mobile POS)
        //   'pos_test' → smoke-test tickets, excluded from every count
        //   anything else → online (site checkout, retail, etc.)
        // All aggregates share the same $eventScope + valid/used filter
        // as $totalTickets so the numbers reconcile exactly with the
        // top-line "Vândute" figure the dashboard already surfaces.
        // The two queries below add a LEFT JOIN to orders — the shared
        // $eventScope closure references event_id / order_id without a
        // table qualifier, which becomes ambiguous under the JOIN
        // ("column reference event_id is ambiguous"). A JOIN-safe copy
        // of the scope uses tickets.-prefixed columns instead.
        $joinSafeScope = function ($q) use ($event) {
            $q->where(function ($paid) use ($event) {
                $paid->where('tickets.event_id', $event->id)
                    ->whereHas('order', fn ($oq) => $oq->whereIn('status', ['paid', 'confirmed', 'completed']));
            })
            ->orWhere(function ($inv) use ($event) {
                $inv->whereNull('tickets.order_id')
                    ->where(function ($ev) use ($event) {
                        $ev->where('tickets.event_id', $event->id)
                            ->orWhereHas('ticketType', fn ($ttq) => $ttq->where('event_id', $event->id));
                    });
            });
        };

        $bySource = \App\Models\Ticket::where($joinSafeScope)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->leftJoin('orders', 'orders.id', '=', 'tickets.order_id')
            ->where(function ($q) {
                $q->whereNull('orders.source')
                    ->orWhere('orders.source', '!=', 'pos_test');
            })
            ->selectRaw("
                CASE
                    WHEN orders.source = 'pos_app' THEN 'door'
                    ELSE 'online'
                END AS bucket,
                COUNT(*) AS c
            ")
            ->groupBy('bucket')
            ->pluck('c', 'bucket');

        $onlineCount = (int) ($bySource['online'] ?? 0);
        $doorCount = (int) ($bySource['door'] ?? 0);

        // Effective capacity for the Capacitate progress card. Prefer the
        // event's own capacity column; when the organizer left it null
        // (very common on Ambilet — most old events only carry per-
        // ticket-type quotas) fall back to Σ(ticket_types.capacity),
        // treating a -1 sentinel ("unlimited") as unbounded and so
        // excluding it from the sum. Zero means "no capacity known" and
        // the frontend hides the card.
        $effectiveCapacity = (int) ($event->capacity ?? 0);
        if ($effectiveCapacity <= 0) {
            $effectiveCapacity = (int) \App\Models\TicketType::query()
                ->where('event_id', $event->id)
                ->where(function ($q) {
                    // Skip -1 sentinels and skip test / invitation types
                    // which aren't meant to count against total capacity.
                    $q->where('capacity', '>', 0);
                })
                ->where(function ($q) {
                    $q->whereRaw("(meta->>'is_test')::boolean IS DISTINCT FROM true");
                })
                ->where(function ($q) {
                    $q->whereRaw("(meta->>'is_invitation')::boolean IS DISTINCT FROM true")
                        ->orWhereNull('meta');
                })
                ->sum('capacity');
        }

        // Per-source × per-ticket-type breakdown. Same base scope, joins
        // tickets → ticket_types for name/color rendering. Zero-priced
        // rows (invitations) still show up so the ticket picker on the
        // dashboard is a true reflection of what got scanned.
        $bySourceAndType = \App\Models\Ticket::where($joinSafeScope)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->leftJoin('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->where(function ($q) {
                $q->whereNull('orders.source')
                    ->orWhere('orders.source', '!=', 'pos_test');
            })
            ->selectRaw("
                CASE WHEN orders.source = 'pos_app' THEN 'door' ELSE 'online' END AS bucket,
                ticket_types.id AS ticket_type_id,
                ticket_types.name AS name,
                ticket_types.color AS color,
                COUNT(*) AS sold_count
            ")
            ->groupBy('bucket', 'ticket_types.id', 'ticket_types.name', 'ticket_types.color')
            ->orderByDesc('sold_count')
            ->get();

        $byOnline = [];
        $byDoor = [];
        foreach ($bySourceAndType as $row) {
            $entry = [
                'ticket_type_id' => (int) $row->ticket_type_id,
                'name' => (string) $row->name,
                'color' => $row->color ?? null,
                'sold_count' => (int) $row->sold_count,
            ];
            if ($row->bucket === 'door') {
                $byDoor[] = $entry;
            } else {
                $byOnline[] = $entry;
            }
        }

        return $this->paginated($tickets, function ($ticket) {
            $ticketType = $ticket->ticketType;
            $ticketMeta = is_array($ticket->meta) ? $ticket->meta : [];
            $attendeePhone = $ticketMeta['attendee_phone'] ?? $ticketMeta['beneficiary_phone'] ?? null;
            $isInvitation = !empty($ticketMeta['is_invitation']);

            // Customer / order context — invitations have no order; pull beneficiary
            // info from meta instead.
            $customerName = null;
            $customerEmail = null;
            $customerPhone = null;
            $orderNumber = null;
            $orderSource = null;

            if ($ticket->order) {
                $customer = $ticket->order->marketplaceCustomer;
                $customerName = $customer
                    ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
                    : $ticket->order->customer_name;
                $customerEmail = $customer?->email ?? $ticket->order->customer_email;
                $customerPhone = $customer?->phone ?? $ticket->order->customer_phone;
                $orderNumber = $ticket->order->order_number;
                $orderSource = $ticket->order->source;
                if (!$isInvitation) {
                    $isInvitation = !empty($ticket->order->meta['is_invitation']);
                }
            } else {
                $beneficiary = $ticketMeta['beneficiary'] ?? [];
                $customerName = $beneficiary['name'] ?? $ticket->attendee_name ?? null;
                $customerEmail = $beneficiary['email'] ?? null;
                $customerPhone = $beneficiary['phone'] ?? $attendeePhone;
                $orderSource = 'invitation';
            }

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
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                ],
                'attendee' => [
                    'name' => $ticket->attendee_name,
                    'email' => $ticket->attendee_email,
                    'phone' => $attendeePhone,
                ],
                'order_number' => $orderNumber,
                'source' => $orderSource,
                'is_invitation' => $isInvitation,
                'purchased_at' => $ticket->created_at->toIso8601String(),
            ];
        }, [
            'stats' => [
                'total' => $totalTickets,
                'checked_in' => $checkedInCount,
                'not_checked_in' => $totalTickets - $checkedInCount,
                'check_in_rate' => $totalTickets > 0 ? round(($checkedInCount / $totalTickets) * 100, 1) : 0,
                'online_count' => $onlineCount,
                'door_count' => $doorCount,
                'by_source_and_type' => [
                    'online' => $byOnline,
                    'door' => $byDoor,
                ],
                // Effective capacity for the Capacitate card. Consumers
                // MUST prefer this over the raw event.capacity column
                // because it handles the Ambilet-common "no event-level
                // capacity, only per-type" case (falls back to
                // Σ ticket_types.capacity, excluding test / invitation
                // types and -1 unlimited sentinels).
                'capacity' => $effectiveCapacity,
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
            ->pluck('id')
            ->all();

        // Count tickets from paid/confirmed/completed orders (confirmed = POS cash)
        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        // Apply event filter early so the same set is used for both the
        // listing query and the stats. When no event_id is given we still
        // scope to the organizer's events.
        $scopedEventIds = $request->has('event_id')
            ? array_values(array_intersect([(int) $request->event_id], $eventIds))
            : $eventIds;

        // Scope tickets via tickets.event_id — NOT order.event_id. Mixed-cart
        // orders carry tickets for multiple events but the order has only a
        // single event_id field, so attribution by order.event_id loses or
        // misclassifies tickets. tickets.event_id is the per-ticket truth.
        // The organizer-owned $eventIds gate above already enforces scoping
        // (only events this organizer owns end up in $scopedEventIds), so we
        // intentionally drop the order.marketplace_organizer_id check too —
        // it would re-exclude legitimate items in mixed-cart orders whose
        // "primary" event lives under a different organizer.
        $branchTickets = function ($q) use ($scopedEventIds, $validOrderStatuses) {
            $q->where(function ($paid) use ($scopedEventIds, $validOrderStatuses) {
                $paid->whereIn('event_id', $scopedEventIds)
                    ->whereHas('order', fn ($oq) => $oq->whereIn('status', $validOrderStatuses));
            })
            ->orWhere(function ($inv) use ($scopedEventIds) {
                // Invitations: order_id null. tickets.event_id is normally
                // populated; ticketType.event_id is a fallback for any
                // legacy invite rows that pre-date the stamping.
                $inv->whereNull('order_id')
                    ->where(function ($ev) use ($scopedEventIds) {
                        $ev->whereIn('event_id', $scopedEventIds)
                            ->orWhereHas('ticketType', fn ($ttq) => $ttq->whereIn('event_id', $scopedEventIds));
                    });
            });
        };

        $query = \App\Models\Ticket::where($branchTickets)
            ->with(['order.marketplaceCustomer', 'order.event', 'ticketType.event']);

        if ($request->has('checked_in')) {
            if ($request->checked_in === 'checked_in' || $request->checked_in === 'true' || $request->checked_in === '1') {
                $query->whereNotNull('checked_in_at');
            } elseif ($request->checked_in === 'not_checked' || $request->checked_in === 'false' || $request->checked_in === '0') {
                $query->whereNull('checked_in_at');
            }
        }

        // Search functionality disabled for privacy

        $query->orderBy('created_at', 'desc');

        // Stats — use the same union (orders + invitations) so totals match
        // what's actually rendered in the table.
        $statsQuery = \App\Models\Ticket::where($branchTickets)
            ->whereIn('status', ['valid', 'used']);

        $totalTickets = $statsQuery->count();
        $checkedInCount = (clone $statsQuery)->whereNotNull('checked_in_at')->count();

        // Revenue intentionally only counts ORDER-bound tickets — invitations
        // are zero-value by design, so adding them would just be a no-op.
        // Scope by tickets.event_id (per-ticket truth) and by order status
        // only — the order's marketplace_organizer_id is intentionally not
        // checked, so mixed-cart orders contribute their relevant items to
        // the right organizer's revenue.
        $revenue = (float) \App\Models\Ticket::whereIn('event_id', $scopedEventIds)
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', $validOrderStatuses))
            ->sum('price');

        // Unique orders touching this organizer's events. Counted via the
        // tickets table so a mixed-cart order whose "primary" event_id
        // belongs elsewhere is still counted once when any of its items
        // matches.
        $ordersCount = (int) \App\Models\Ticket::whereIn('event_id', $scopedEventIds)
            ->whereIn('status', ['valid', 'used'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', $validOrderStatuses))
            ->whereNotNull('order_id')
            ->distinct('order_id')
            ->count('order_id');

        $tickets = $query->take(200)->get();

        // Lookup of event titles by id so invitation tickets (which carry
        // no order.event) can still display the event name.
        $eventTitles = [];
        if (!empty($scopedEventIds)) {
            foreach (Event::whereIn('id', $scopedEventIds)->get(['id', 'title']) as $ev) {
                $eventTitles[$ev->id] = $ev->getTranslation('title', 'ro')
                    ?: $ev->getTranslation('title', 'en')
                    ?: $ev->getTranslation('title')
                    ?: 'Unknown Event';
            }
        }

        $participants = $tickets->map(function ($ticket) use ($eventTitles) {
            $ticketMeta = is_array($ticket->meta) ? $ticket->meta : [];
            $isInvitation = !empty($ticketMeta['is_invitation'])
                || (!$ticket->order && $ticket->ticketType?->event_id);

            // Common fields: customer / event title / phone — pulled from the
            // order when present, from invite meta otherwise.
            $rawName = 'Invitat';
            $rawEmail = '';
            $phone = '';
            $eventId = $ticket->order?->event?->id
                ?? $ticket->ticketType?->event_id;
            $eventTitle = $eventTitles[$eventId] ?? 'Unknown Event';
            $orderId = $ticket->order?->id;
            $orderNumber = $ticket->order?->order_number;
            $orderDate = $ticket->order?->created_at?->toIso8601String() ?? $ticket->created_at?->toIso8601String();

            if ($ticket->order) {
                $customer = $ticket->order->marketplaceCustomer;
                $rawName = $customer
                    ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
                    : ($ticket->order->customer_name ?? 'Unknown');
                $rawEmail = $customer?->email ?? $ticket->order->customer_email ?? '';
                $phone = $customer?->phone ?? $ticket->order->customer_phone ?? '';
            } elseif ($isInvitation) {
                $beneficiary = $ticketMeta['beneficiary'] ?? [];
                $rawName = $beneficiary['name'] ?? $ticket->attendee_name ?? 'Invitat';
                $rawEmail = $beneficiary['email'] ?? $ticket->attendee_email ?? '';
                $phone = $beneficiary['phone'] ?? '';
            }

            return [
                'id' => $ticket->id,
                'ticket_id' => $ticket->id,
                'name' => $rawName ?: 'Invitat',
                'email' => $rawEmail ? $this->maskEmail($rawEmail) : '',
                'phone' => $phone,
                'event' => $eventTitle,
                'event_id' => $eventId,
                'ticket_type' => $ticket->ticketType?->name ?? ($isInvitation ? 'Invitatie' : 'Standard'),
                'ticket_code' => $ticket->barcode,
                'control_code' => $ticket->code,
                'seat_label' => $ticket->seat_label ?? null,
                'checked_in' => $ticket->checked_in_at !== null,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'order_date' => $orderDate,
                'is_invitation' => $isInvitation,
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
     * Check in a ticket by barcode (across all organizer's events).
     *
     * Accepts:
     *  - regular tickets (have an order with status paid/confirmed/completed)
     *  - invitation tickets (order_id null, meta.is_invitation=true), which
     *    resolve their event via TicketType.event_id
     *
     * Code matching is case-insensitive so QR payloads from different
     * generators don't fail on case alone. The mobile app strips the
     * /t/{code} URL form before sending; the backend further accepts a raw
     * URL just in case.
     */
    public function checkInByCode(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $request->validate([
            'ticket_code' => 'required|string',
        ]);

        $rawCode = trim((string) $request->ticket_code);

        // Rezolvam best-guess event id pentru logging (organizer poate avea
        // mai multe evenimente; alegem cel mai recent/activ ca fallback cand
        // scanner-ul nu trimite explicit event_id).
        $currentEventId = $this->resolveCurrentEventIdForOrganizer($request, $organizer);

        // Staff QR-uri sunt prefixate cu STAFF-{12-hex}. Deviem catre flow-ul
        // de staff (check-in nelimitat, log separat in leisure_staff_checkins).
        if (str_starts_with(strtoupper($rawCode), \App\Models\LeisureStaffMember::QR_PREFIX)) {
            return $this->checkInStaff($request, $rawCode, $organizer, $currentEventId);
        }

        $barcode = $this->normalizeTicketCode($request->ticket_code);

        // All events owned by this organizer on their marketplace
        $eventIds = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->pluck('id');

        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        // Match any ticket with this code/barcode, regardless of order. We'll
        // verify ownership in PHP — invitations are scoped via TicketType,
        // regular tickets via order.
        $ticket = \App\Models\Ticket::with('ticketType', 'order.marketplaceCustomer')
            ->where(function ($q) use ($barcode) {
                $q->whereRaw('LOWER(barcode) = ?', [strtolower($barcode)])
                  ->orWhereRaw('LOWER(code) = ?', [strtolower($barcode)]);
            })
            ->first();

        if (!$ticket) {
            $this->logScanAttempt($request, $currentEventId ?: $eventIds->first(), $organizer, $barcode, 'invalid', 'not_found');
            return $this->checkInExternalTicket($barcode, $eventIds->toArray(), $organizer);
        }

        $isInvitation = is_array($ticket->meta) && !empty($ticket->meta['is_invitation']);

        // Resolve the event for this ticket (direct event_id, then via
        // TicketType.event_id which is reliably set for invitations)
        $resolvedEventId = $ticket->event_id ?? $ticket->ticketType?->event_id;
        if (!$resolvedEventId) {
            $this->logScanAttempt($request, $currentEventId ?: $eventIds->first(), $organizer, $barcode, 'invalid', 'no_event');
            return $this->error('Ticket is not linked to any event', 404);
        }
        if (!$eventIds->contains((int) $resolvedEventId)) {
            $this->logScanAttempt($request, $resolvedEventId, $organizer, $barcode, 'invalid', 'not_yours');
            return $this->error('Ticket is not for one of your events', 403);
        }

        // For regular tickets, the order must be in a valid status. Invitations
        // have no order so we skip this check.
        if (!$isInvitation) {
            if (!$ticket->order || !in_array($ticket->order->status, $validOrderStatuses, true)) {
                $this->logScanAttempt($request, $resolvedEventId, $organizer, $barcode, 'invalid', 'order_' . ($ticket->order?->status ?? 'missing'));
                return $this->error('Ticket order is not in a valid status', 400);
            }
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            $this->logScanAttempt($request, $resolvedEventId, $organizer, $barcode, 'invalid', 'ticket_' . $ticket->status);
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        // Venue-owner private notes (visible to marketplace scanners per product requirement)
        $venueNotes = $this->resolveVenueNotesForTicket($ticket);

        if ($ticket->checked_in_at) {
            $this->logScanAttempt($request, $resolvedEventId, $organizer, $barcode, 'duplicate', 'already_checked_in');
            $duplicate = $this->buildTicketScanPayload($ticket, $isInvitation);
            return response()->json(array_merge([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
                'venue_notes' => $venueNotes,
            ], $duplicate), 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $organizer->contact_name ?? $organizer->name,
            'checked_in_via' => 'organizer_app',
        ]);

        $payload = $this->buildTicketScanPayload($ticket, $isInvitation);
        $payload['venue_notes'] = $venueNotes;

        return $this->success($payload, 'Ticket checked in successfully');
    }

    /**
     * Strip whitespace and any /t/{code} or /verify/{code} URL wrapper, just
     * in case the mobile sends the raw QR payload.
     */
    /**
     * Log o scanare esuata sau duplicat pentru raportare in
     * /organizator/leisure-raport sectiunea 'Scanari'. Silent-fail — logging-ul
     * nu poate rupe fluxul de check-in.
     */
    protected function logScanAttempt(Request $request, ?int $eventId, ?\App\Models\MarketplaceOrganizer $organizer, string $code, string $result, ?string $reason = null): void
    {
        if (!$eventId) return;
        try {
            \App\Models\LeisureScanAttempt::create([
                'event_id' => $eventId,
                'marketplace_organizer_id' => $organizer?->id,
                'attempted_code' => substr($code, 0, 128),
                'result' => $result,
                'reason' => $reason ? substr($reason, 0, 255) : null,
                'ip_address' => $request->ip(),
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) { /* silent */ }
    }

    /**
     * Rezolva "event-ul curent" pentru un scan de la un organizer:
     *   1. event_id explicit din request (validat sa apartina organizer-ului)
     *   2. cel mai recent event care nu s-a terminat (start_date DESC)
     *   3. altfel: cel mai recent event indiferent de data
     *
     * Folosit ca fallback consistent pentru staff check-ins si scan attempts
     * cand scanner-ul (Kiosk / mobile) nu trimite event_id explicit.
     */
    protected function resolveCurrentEventIdForOrganizer(Request $request, ?\App\Models\MarketplaceOrganizer $organizer): ?int
    {
        if (!$organizer) return null;

        $requested = $request->input('event_id');
        if ($requested) {
            $exists = Event::where('id', (int) $requested)
                ->where('marketplace_organizer_id', $organizer->id)
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->exists();
            if ($exists) return (int) $requested;
        }

        // Preferat: cel mai recent event care nu s-a incheiat inca (event_date sau
        // range_end_date >= azi). Coloanele Event: 'event_date' (single day),
        // 'range_start_date'/'range_end_date' (range), 'recurring_start_date'.
        $today = now('Europe/Bucharest')->toDateString();
        $current = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where(function ($q) use ($today) {
                $q->where('event_date', '>=', $today)
                  ->orWhere('range_end_date', '>=', $today)
                  ->orWhere('recurring_start_date', '>=', $today);
            })
            ->orderByDesc('id')
            ->value('id');

        if ($current) return (int) $current;

        // Fallback total: cel mai recent event (dupa id). Pentru Sf. Ana / leisure
        // organizer cu un singur event activ, asta pica pe eventul curent.
        $latest = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->orderByDesc('id')
            ->value('id');

        return $latest ? (int) $latest : null;
    }

    protected function normalizeTicketCode(string $raw): string
    {
        $trimmed = trim($raw);
        // /v/CODE — shortlink folosit de bilete emise POS
        // (buildTicketCommands in pos-printer.js: https://ambilet.ro/v/CODE?p=pos)
        if (preg_match('#/v/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        if (preg_match('#/t/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        if (preg_match('#/verify/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        return $trimmed;
    }

    /**
     * Build the ticket+customer+order payload used by both the success and
     * already-checked-in responses. Handles invitations (no order) by reading
     * beneficiary info from meta.
     */
    protected function buildTicketScanPayload(\App\Models\Ticket $ticket, bool $isInvitation): array
    {
        $seatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
        $beneficiary = is_array($ticket->meta) ? ($ticket->meta['beneficiary'] ?? []) : [];

        $customerName = null;
        $customerEmail = null;
        if (!$isInvitation && $ticket->order) {
            $marketplaceCustomer = $ticket->order->marketplaceCustomer;
            $customerName = $marketplaceCustomer
                ? trim(($marketplaceCustomer->first_name ?? '') . ' ' . ($marketplaceCustomer->last_name ?? ''))
                : $ticket->order->customer_name;
            $customerEmail = $marketplaceCustomer?->email ?? $ticket->order->customer_email;
        } else {
            $customerName = $beneficiary['name'] ?? $ticket->attendee_name ?? null;
            $customerEmail = $beneficiary['email'] ?? null;
        }

        return [
            'ticket' => [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->ticketType?->name,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'checked_in_by' => $ticket->checked_in_by,
                'seat_label' => $ticket->seat_label,
                'section' => $seatDetails['section_name'] ?? null,
                'row' => $seatDetails['row_label'] ?? null,
                'seat' => $seatDetails['seat_number'] ?? null,
                'attendee_name' => $ticket->attendee_name,
                'is_invitation' => $isInvitation,
            ],
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
            ],
            'order' => $ticket->order ? [
                'source' => $ticket->order->source ?? 'online',
                'customer_name' => $ticket->order->customer_name,
            ] : [
                'source' => 'invitation',
                'customer_name' => $beneficiary['name'] ?? null,
            ],
        ];
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

        $validOrderStatuses = ['paid', 'confirmed', 'completed'];
        $normalized = $this->normalizeTicketCode($barcode);

        $ticket = \App\Models\Ticket::with('ticketType', 'order.marketplaceCustomer')
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(barcode) = ?', [strtolower($normalized)])
                  ->orWhereRaw('LOWER(code) = ?', [strtolower($normalized)]);
            })
            ->first();

        if (!$ticket) {
            return $this->checkInExternalTicket($normalized, [$event->id], $organizer);
        }

        $isInvitation = is_array($ticket->meta) && !empty($ticket->meta['is_invitation']);

        // Verify the ticket belongs to this specific event — invitation event
        // resolved via TicketType.event_id, regular tickets via order.event_id.
        $resolvedEventId = $ticket->event_id ?? $ticket->ticketType?->event_id;
        if (!$isInvitation && (!$ticket->order || !in_array($ticket->order->status, $validOrderStatuses, true))) {
            return $this->error('Ticket order is not in a valid status', 400);
        }
        if ($resolvedEventId === null || (int) $resolvedEventId !== (int) $event->id) {
            // Fall through: try external (mainly for non-tixello tickets shared
            // with the same QR scanner workflow)
            return $this->checkInExternalTicket($normalized, [$event->id], $organizer);
        }

        if ($ticket->status === 'cancelled' || $ticket->status === 'refunded') {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        $venueNotes = $this->resolveVenueNotesForTicket($ticket);

        if ($ticket->checked_in_at) {
            $duplicate = $this->buildTicketScanPayload($ticket, $isInvitation);
            return response()->json(array_merge([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
                'venue_notes' => $venueNotes,
            ], $duplicate), 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $organizer->contact_name ?? $organizer->name,
            'checked_in_via' => 'organizer_app',
        ]);

        $payload = $this->buildTicketScanPayload($ticket, $isInvitation);
        $payload['venue_notes'] = $venueNotes;

        return $this->success($payload, 'Ticket checked in successfully');
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
     * Check-in pentru angajat permanent (QR fix prefixat cu STAFF-).
     * Diferă de check-in bilet:
     *   - Nu există limită de scanări (angajatul vine pe ture)
     *   - Logul merge în leisure_staff_checkins (separat de Ticket.checked_in_at)
     *   - Răspunsul include numele + poziția pentru afișare în scanner UI
     *
     * Verificări:
     *   - QR trebuie să existe în leisure_staff_members
     *   - Staff-ul trebuie să fie active=true
     *   - Staff-ul trebuie să aparțină organizer-ului autenticat (scoping)
     */
    protected function checkInStaff(Request $request, string $rawCode, $organizer, ?int $fallbackEventId = null): JsonResponse
    {
        $qrCode = strtoupper(trim($rawCode));

        // event_id: parametru explicit din request (prioritate), altfel fallback
        // catre event-ul curent al organizer-ului (rezolvat de callerul checkInByCode).
        // Fara asta, leisure_staff_checkins.event_id ramane NULL si scan-urile de
        // staff nu apar in modalul "Detalii scanari" pe zi (filtreaza dupa event_id).
        $requestedEventId = $request->input('event_id') ? (int) $request->input('event_id') : null;
        $eventId = $requestedEventId ?: $fallbackEventId;

        $staff = \App\Models\LeisureStaffMember::where('qr_code', $qrCode)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$staff) {
            $this->logScanAttempt($request, $eventId, $organizer, $qrCode, 'invalid', 'staff_qr_not_found');
            return $this->error('Cod QR de personal necunoscut sau nu aparține organizatorului.', 404);
        }
        if (!$staff->active) {
            $this->logScanAttempt($request, $eventId, $organizer, $qrCode, 'invalid', 'staff_inactive');
            return $this->error('Angajat dezactivat: ' . $staff->full_name, 403);
        }

        $location = $request->input('gate_label') ?: $request->input('location');

        $checkin = \App\Models\LeisureStaffCheckin::create([
            'staff_member_id'    => $staff->id,
            'event_id'           => $eventId,
            'scanned_by_user_id' => auth()->id(),
            'location'           => $location ? substr((string) $location, 0, 120) : null,
            'ip_address'         => $request->ip(),
            'user_agent'         => substr((string) $request->userAgent(), 0, 255),
            'checked_in_at'      => now(),
        ]);

        $todayCount = \App\Models\LeisureStaffCheckin::where('staff_member_id', $staff->id)
            ->whereDate('checked_in_at', now()->toDateString())
            ->count();

        return $this->success([
            'is_staff'       => true,
            'staff' => [
                'id'         => $staff->id,
                'full_name'  => $staff->full_name,
                'position'   => $staff->position,
                'phone'      => $staff->phone,
            ],
            'checkin' => [
                'id'             => $checkin->id,
                'checked_in_at'  => $checkin->checked_in_at->toIso8601String(),
                'today_count'    => $todayCount,
            ],
        ], '✓ Acces personal: ' . $staff->full_name . ($staff->position ? ' (' . $staff->position . ')' : ''));
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
            // ExternalTicket has a checked_in_via column too once the
            // migration adds it; the update is a no-op if the column is
            // missing because the model doesn't have it in $fillable.
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

        $validOrderStatuses = ['paid', 'confirmed', 'completed'];

        // Same scoping as the listing endpoint: tickets.event_id (per-ticket
        // truth, handles mixed-cart orders) plus an invitations branch for
        // tickets without an order. Ordered chronologically (newest first)
        // since the export starts with the Purchased At column.
        $tickets = \App\Models\Ticket::where(function ($q) use ($event, $validOrderStatuses) {
                $q->where(function ($paid) use ($event, $validOrderStatuses) {
                    $paid->where('event_id', $event->id)
                        ->whereHas('order', fn ($oq) => $oq->whereIn('status', $validOrderStatuses));
                })
                ->orWhere(function ($inv) use ($event) {
                    $inv->whereNull('order_id')
                        ->where(function ($ev) use ($event) {
                            $ev->where('event_id', $event->id)
                                ->orWhereHas('ticketType', fn ($ttq) => $ttq->where('event_id', $event->id));
                        });
                });
            })
            ->with(['order.marketplaceCustomer', 'ticketType'])
            ->orderByDesc('created_at')
            ->get();

        $filename = $this->buildParticipantsFilename($event);

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Data cumparare',
                'Cod bilet',
                'Tip bilet',
                'Sectiune',
                'Rand',
                'Loc',
                'Net bilet',
                'Nume client',
                'Telefon client',
                'Numar comanda',
                'Check-in',
                'Data check-in',
            ], escape: '\\');

            foreach ($tickets as $ticket) {
                $customer = $ticket->order?->marketplaceCustomer;
                $ticketType = $ticket->ticketType;
                $details = $ticket->getSeatDetails();
                $isInvitation = !$ticket->order;
                $meta = is_array($ticket->meta) ? $ticket->meta : [];

                if ($customer) {
                    $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                    $customerPhone = $customer->phone ?? '';
                } elseif ($ticket->order) {
                    $customerName = $ticket->order->customer_name ?? '';
                    $customerPhone = $ticket->order->customer_phone ?? '';
                } else {
                    $beneficiary = $meta['beneficiary'] ?? [];
                    $customerName = $beneficiary['name'] ?? $ticket->attendee_name ?? 'Invitat';
                    $customerPhone = $beneficiary['phone'] ?? $meta['attendee_phone'] ?? '';
                }

                // Net = price minus baked-in commission for included / POS
                // orders; equals price for on_top orders. See
                // Ticket::getNetPrice() for the resolution table.
                fputcsv($handle, [
                    $ticket->created_at->format('Y-m-d H:i:s'),
                    $ticket->code,
                    $ticketType?->name ?? ($isInvitation ? 'Invitatie' : 'N/A'),
                    $details['section_name'] ?? '',
                    $details['row_label'] ?? '',
                    $details['seat_number'] ?? '',
                    number_format($ticket->getNetPrice(), 2, '.', ''),
                    $customerName,
                    $customerPhone,
                    $ticket->order?->order_number ?? '',
                    $ticket->checked_in_at ? 'Da' : 'Nu',
                    $ticket->checked_in_at?->format('Y-m-d H:i:s') ?? '',
                ], escape: '\\');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

        $format = $request->input('format', 'csv');
        $eventId = $request->input('event_id');

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
        $eventIdsArr = $eventIds->all();

        // Same tickets.event_id-based scoping as the listing endpoint —
        // mixed-cart orders attribute their items via tickets.event_id, and
        // invitations (no order) are pulled in via the second branch so the
        // export matches what the operator sees on the page.
        $tickets = \App\Models\Ticket::where(function ($q) use ($eventIdsArr, $validOrderStatuses) {
                $q->where(function ($paid) use ($eventIdsArr, $validOrderStatuses) {
                    $paid->whereIn('event_id', $eventIdsArr)
                        ->whereHas('order', fn ($oq) => $oq->whereIn('status', $validOrderStatuses));
                })
                ->orWhere(function ($inv) use ($eventIdsArr) {
                    $inv->whereNull('order_id')
                        ->where(function ($ev) use ($eventIdsArr) {
                            $ev->whereIn('event_id', $eventIdsArr)
                                ->orWhereHas('ticketType', fn ($ttq) => $ttq->whereIn('event_id', $eventIdsArr));
                        });
                });
            })
            ->with(['order.marketplaceCustomer', 'order.event', 'ticketType.event'])
            ->orderByDesc('created_at')
            ->get();

        // Cache event titles so we can render invitation tickets (no order)
        // and avoid re-translating per row. Keys = event id.
        $eventTitles = [];
        foreach (Event::whereIn('id', $eventIdsArr)->get(['id', 'title']) as $ev) {
            $eventTitles[$ev->id] = $ev->getTranslation('title', 'ro')
                ?: $ev->getTranslation('title', 'en')
                ?: $ev->getTranslation('title')
                ?: 'Unknown Event';
        }

        $rows = $tickets->map(function ($ticket) use ($eventTitles) {
            $customer = $ticket->order?->marketplaceCustomer;
            $eventId = $ticket->event_id ?? $ticket->order?->event_id ?? $ticket->ticketType?->event_id;
            $eventTitle = $eventTitles[$eventId] ?? 'Unknown Event';

            $details = $ticket->getSeatDetails();
            $isInvitation = !$ticket->order;
            $meta = is_array($ticket->meta) ? $ticket->meta : [];

            // Customer fallback for invitation rows (no order/customer record).
            if ($customer) {
                $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                $customerPhone = $customer->phone ?? '';
            } elseif ($ticket->order) {
                $customerName = $ticket->order->customer_name ?? 'N/A';
                $customerPhone = $ticket->order->customer_phone ?? '';
            } else {
                $beneficiary = $meta['beneficiary'] ?? [];
                $customerName = $beneficiary['name'] ?? $ticket->attendee_name ?? 'Invitat';
                $customerPhone = $beneficiary['phone'] ?? $meta['attendee_phone'] ?? '';
            }

            return [
                'Data cumparare' => $ticket->created_at->format('Y-m-d H:i:s'),
                'Cod bilet' => $ticket->code,
                'Eveniment' => $eventTitle,
                'Tip bilet' => $ticket->ticketType?->name ?? ($isInvitation ? 'Invitatie' : 'Standard'),
                'Sectiune' => $details['section_name'] ?? '',
                'Rand' => $details['row_label'] ?? '',
                'Loc' => $details['seat_number'] ?? '',
                // Net = price minus baked-in commission for included / POS
                // orders; equals price for on_top orders. See
                // Ticket::getNetPrice() for the resolution table.
                'Net bilet' => number_format($ticket->getNetPrice(), 2, '.', ''),
                'Nume client' => $customerName ?: ($isInvitation ? 'Invitat' : 'N/A'),
                'Telefon client' => $customerPhone,
                'Numar comanda' => $ticket->order?->order_number ?? '',
                'Check-in' => $ticket->checked_in_at ? 'Da' : 'Nu',
                'Data check-in' => $ticket->checked_in_at?->format('Y-m-d H:i:s') ?? '',
            ];
        });

        // Build a filename anchored to the selected event when one is set
        // (Vânzări always passes event_id, so this is the common path); for
        // a cross-event dump fall back to today's date.
        if ($eventId) {
            $event = Event::find($eventId);
            $filename = $event ? $this->buildParticipantsFilenameBase($event) : ('participanti-' . now()->format('Y-m-d'));
        } else {
            $filename = 'participanti-' . now()->format('Y-m-d');
        }

        if ($format === 'xlsx') {
            return $this->exportToXlsx($rows->toArray(), $filename);
        }

        return $this->exportToCsv($rows->toArray(), $filename);
    }

    /**
     * Filename base (no extension): "{slug}-{date}-participanti".
     * Uses Romanian title for the slug and the event's actual date (single-
     * day or range start), falling back to today if neither is set.
     */
    protected function buildParticipantsFilenameBase(Event $event): string
    {
        $title = $event->getTranslation('title', 'ro')
            ?: $event->getTranslation('title', 'en')
            ?: $event->getTranslation('title')
            ?: 'eveniment';
        $slug = \Illuminate\Support\Str::slug($title) ?: ($event->slug ?? 'eveniment');

        $date = $event->event_date ?? $event->range_start_date ?? now();
        $dateStr = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : (string) $date;

        return "{$slug}-{$dateStr}-participanti";
    }

    /**
     * Filename WITH extension — convenience wrapper around the base for
     * the per-event export path which uses streamDownload directly.
     */
    protected function buildParticipantsFilename(Event $event): string
    {
        return $this->buildParticipantsFilenameBase($event) . '.csv';
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
                fputcsv($handle, array_keys($rows[0]), ',', '"', '');

                // Data
                foreach ($rows as $row) {
                    fputcsv($handle, array_values($row), ',', '"', '');
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

        $period = $request->input('period', '30d');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Channel filter: null = all channels (default), or marketplace|whitelabel|embed_widget.
        // When set, every metric is filtered by channel:
        //  - page_views / unique_visitors / traffic_sources / top_locations come
        //    from core_customer_events.channel (string column we added 2026-06-18)
        //  - tickets_sold / chart_tickets / chart_revenue / recent_sales come
        //    from orders.channel (introduced by E6 with values
        //    online/pos_fixed/pos_mobile/embed/partner_app; whitelabel was
        //    appended 2026-06-18 by MarketplaceTrackingController).
        //
        // UI semantics:
        //  - 'marketplace' (default selection) = every channel EXCEPT 'whitelabel'
        //    (POS sales count as marketplace activity for the organizer)
        //  - 'whitelabel'  = only orders.channel = 'whitelabel'
        // The orderChannelClause / trackingChannelClause closures below
        // centralize that translation so every query stays consistent.
        $channel = $request->input('channel');
        if ($channel && !in_array($channel, ['marketplace', 'whitelabel'], true)) {
            $channel = null;
        }
        $orderChannelClause = function ($q) use ($channel) {
            if ($channel === 'whitelabel') {
                $q->where('channel', 'whitelabel');
            } elseif ($channel === 'marketplace') {
                $q->where(fn ($qq) => $qq->where('channel', '!=', 'whitelabel')->orWhereNull('channel'));
            }
        };
        $trackingChannelClause = function ($q) use ($channel) {
            if ($channel === 'whitelabel') {
                $q->where('channel', 'whitelabel');
            } elseif ($channel === 'marketplace') {
                $q->where(fn ($qq) => $qq->where('channel', '!=', 'whitelabel')->orWhereNull('channel'));
            }
        };

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

        // Scoped orders query: only this organizer's marketplace orders.
        // When a channel filter is active, scope by orders.channel too — Purchase
        // events stamp the column when they fire (see MarketplaceTrackingController).
        $scopedOrders = fn () => $event->orders()
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', $validStatuses)
            ->when($channel, fn ($q) => $orderChannelClause($q));

        // Base query for orders in the range (used for chart data & period comparisons)
        $ordersQuery = $scopedOrders()->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        // Overview metrics — always all-time (not filtered by period)
        $allTimeQuery = $scopedOrders();

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
        // Per-ticket commission (platform's share) using the tt's base price.
        $commissionPerTicket = function (\App\Models\TicketType $tt) use ($defaultRate, $defaultMode): float {
            $basePriceCents = ((int) ($tt->sale_price_cents ?? 0)) > 0
                ? (int) $tt->sale_price_cents
                : (int) ($tt->price_cents ?? 0);
            $basePrice = $basePriceCents / 100;
            return (float) $tt->calculateCommission($basePrice, $defaultRate, $defaultMode);
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
            })
            // Channel scope: tickets inherit their order's channel.
            //   - null channel: no extra filter (keeps POS / invitations).
            //   - 'marketplace': UI semantics say "POS sales count as marketplace
            //     activity". So we keep tickets WITHOUT an order (POS,
            //     invitations) AND tickets whose order matches the marketplace
            //     channel clause. Previously this branch required an order via
            //     whereHas, silently dropping 3 of 28 tickets for the user.
            //   - 'whitelabel': strict — order must exist with channel='whitelabel'.
            ->when($channel === 'marketplace', fn ($q) => $q->where(function ($q2) use ($orderChannelClause) {
                $q2->whereDoesntHave('order')
                   ->orWhereHas('order', fn ($qq) => $orderChannelClause($qq));
            }))
            ->when($channel === 'whitelabel', fn ($q) => $q->whereHas('order', fn ($qq) => $orderChannelClause($qq)));

        // Valid tickets grouped by ticket_type — used for net revenue and per-type counts
        $validCountsByType = (clone $validEventTicketsQuery())
            ->selectRaw('ticket_type_id, COUNT(*) as cnt')
            ->groupBy('ticket_type_id')
            ->pluck('cnt', 'ticket_type_id');

        // Net revenue & commission = sum over VALID tickets of the ticket_type's
        // net price / commission. Uses the ticket_type configured price (ignoring
        // individual ticket.price which can be 0 for invitations/free tickets).
        $netRevenue = 0.0;
        $commissionAmount = 0.0;
        foreach ($event->ticketTypes as $tt) {
            $validCount = (int) ($validCountsByType[$tt->id] ?? 0);
            if ($validCount === 0) continue;
            $netRevenue += $netPricePerTicket($tt) * $validCount;
            $commissionAmount += $commissionPerTicket($tt) * $validCount;
        }
        $netRevenue = round($netRevenue, 2);
        $commissionAmount = round($commissionAmount, 2);

        // Extras attributable to the VALID portion of paid orders (insurance +
        // cultural card surcharge from Order.meta, proportional to valid_gross
        // / Order.subtotal). These go to the platform, not the organizer.
        $extrasValid = 0.0;
        $paidOrders = (clone $allTimeQuery)->get(['id', 'subtotal', 'meta']);
        if ($paidOrders->isNotEmpty()) {
            $paidOrderIds = $paidOrders->pluck('id');
            $validGrossPerOrder = \App\Models\Ticket::whereIn('order_id', $paidOrderIds)
                ->whereIn('status', ['valid', 'used'])
                ->get(['order_id', 'ticket_type_id'])
                ->groupBy('order_id')
                ->map(function ($group) use ($event, $netPricePerTicket, $commissionPerTicket) {
                    $gross = 0.0;
                    foreach ($group as $t) {
                        $tt = $event->ticketTypes->firstWhere('id', $t->ticket_type_id);
                        if (!$tt) continue;
                        // gross per ticket for allocation = net + commission
                        $gross += $netPricePerTicket($tt) + $commissionPerTicket($tt);
                    }
                    return $gross;
                });
            foreach ($paidOrders as $o) {
                $m = is_array($o->meta) ? $o->meta : [];
                $orderExtras = (float) ($m['insurance_amount'] ?? 0) + (float) ($m['cultural_card_surcharge'] ?? 0);
                if ($orderExtras <= 0) continue;
                $orderSubtotal = (float) $o->subtotal;
                $orderValidGross = (float) ($validGrossPerOrder[$o->id] ?? 0);
                $ratio = $orderSubtotal > 0 ? min(1.0, $orderValidGross / $orderSubtotal) : 1.0;
                $extrasValid += $orderExtras * $ratio;
            }
        }
        $extrasValid = round($extrasValid, 2);

        // Gross = what customer paid for VALID tickets only (matches admin's
        // Venituri): net + commission + extras.
        $grossRevenue = round($netRevenue + $commissionAmount + $extrasValid, 2);

        // Refunds
        $refundsTotal = (float) Order::where('event_id', $event->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->sum('total');

        $totalRevenue = $grossRevenue; // Keep for backwards compat (chart/comparison)
        // Bilete vândute = all valid tickets (online + POS + invitations)
        $ticketsSold = (int) (clone $validEventTicketsQuery())->count();

        // Page views — always read from the canonical core_customer_events
        // table. We used to take a fast path through the denormalized
        // $event->views_count counter when no channel filter was set, but
        // that counter lags behind the real table for some events, which
        // produced the bizarre symptom the user reported: switching the
        // dropdown from "Toate" to "Marketplace" made the views go UP
        // (507 → 1310) — impossible since marketplace ⊆ all. Reading the
        // same source for both branches keeps the count monotonically
        // non-increasing across channel narrowing.
        $pageViewQuery = \App\Models\Platform\CoreCustomerEvent::where(function ($q) use ($event) {
            $q->where('event_id', $event->id)
              ->orWhere('marketplace_event_id', $event->id);
        })->where('event_type', 'page_view');
        if ($channel) {
            $trackingChannelClause($pageViewQuery);
        }
        $pageViews = (int) $pageViewQuery->count();
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
        if ($channel) {
            $trackingChannelClause($trackingQuery);
        }

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

        // Top locations — aggregate distinct visitors per country/city from the
        // tracking table. Honors the same channel filter as the rest of the
        // dashboard, so "Whitelabel" only counts visitors that landed on a
        // whitelabel-packaged site. Returns up to 10 buckets ordered by visitors.
        try {
            $topLocations = (clone $trackingQuery)
                ->whereNotNull('country_code')
                ->selectRaw('country_code, city, COUNT(DISTINCT visitor_id) as visitors')
                ->groupBy('country_code', 'city')
                ->orderByDesc('visitors')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    // Aliased to `country` (ISO-2) so the existing
                    // renderGlobeData()/getFlag() in analytics.php picks
                    // it up without a frontend change.
                    'country' => $row->country_code,
                    'country_code' => $row->country_code,
                    'city' => $row->city ?: 'Unknown',
                    'visitors' => (int) $row->visitors,
                ])
                ->toArray();
        } catch (\Throwable $e) {
            \Log::warning('top_locations query failed', ['error' => $e->getMessage()]);
            $topLocations = [];
        }

        // Recent sales
        $recentSales = $scopedOrders()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                $customer = $order->marketplaceCustomer;
                return [
                    'buyer_name' => $customer
                        ? $customer->first_name . ' ' . mb_substr((string) ($customer->last_name ?? ''), 0, 1) . '.'
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
     * Per-staff-member sales report for a single event.
     * Aggregates orders attributed to each POS operator (via
     * order.meta.sold_by) and the residual "Online" bucket for sales that
     * came through the public marketplace without a sold_by tag. Each
     * bucket carries: order count, ticket count, gross revenue, payment
     * method split (cash / card / online), and per-ticket-type breakdown.
     */
    public function staffReport(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = Event::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->with('ticketTypes:id,name,event_id')
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $validStatuses = ['paid', 'confirmed', 'completed'];

        // Pull all qualifying orders + their tickets in one shot. We then
        // do the bucketing in PHP because grouping by a JSON path AND
        // joining ticket_types in SQL is awkward across drivers — and the
        // dataset is bounded by 1 event's order count (rarely > a few
        // thousand even on the biggest organizers).
        $orders = \App\Models\Order::query()
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->whereIn('status', $validStatuses)
            ->whereHas('tickets', fn ($q) => $q->where('event_id', $eventId))
            ->with([
                'tickets' => function ($q) use ($eventId) {
                    $q->where('event_id', $eventId)
                        ->whereIn('status', ['valid', 'used'])
                        ->with('ticketType:id,name');
                },
            ])
            ->orderBy('created_at')
            ->get();

        // Pre-cache User + TeamMember lookups for per-individual labels.
        // Three possible meta keys captured on past + future orders:
        //   - meta.team_member_id (organizer POS, set by OrdersController
        //     from the Sanctum token name "team-member-{id}")
        //   - meta.venue_owner_user_id (venue_owner POS — links to User)
        //   - meta.sold_by (string label, fallback only)
        // We bulk-fetch both tables once to avoid N+1 in the loop.
        $venueOwnerUserIds = $orders
            ->pluck('meta.venue_owner_user_id')
            ->filter()
            ->unique()
            ->map(fn ($v) => (int) $v)
            ->all();
        $userById = !empty($venueOwnerUserIds)
            ? \App\Models\User::whereIn('id', $venueOwnerUserIds)
                ->get(['id', 'name', 'first_name', 'last_name', 'email'])
                ->keyBy('id')
            : collect();

        $teamMemberIds = $orders
            ->pluck('meta.team_member_id')
            ->filter()
            ->unique()
            ->map(fn ($v) => (int) $v)
            ->all();
        $teamMemberById = !empty($teamMemberIds)
            ? \App\Models\MarketplaceOrganizerTeamMember::whereIn('id', $teamMemberIds)
                ->get(['id', 'name', 'email'])
                ->keyBy('id')
            : collect();

        $buckets = []; // keyed by operator label
        $totals = [
            'orders' => 0,
            'tickets' => 0,
            'revenue' => 0.0,
            'cash' => 0.0,
            'card' => 0.0,
            'online' => 0.0,
        ];
        $ticketTypeTotals = []; // [tt_id => ['name'=>..,'count'=>..,'amount'=>..]]

        foreach ($orders as $order) {
            $tickets = $order->tickets;
            if ($tickets->isEmpty()) continue;

            $meta = is_array($order->meta) ? $order->meta : [];
            $soldBy = trim((string) ($meta['sold_by'] ?? ''));
            $source = $order->source ?? 'marketplace';

            // Operator label resolution (most specific → least specific):
            //   1. meta.team_member_id (organizer POS) → look up team member
            //      name in marketplace_organizer_team_members.
            //   2. meta.venue_owner_user_id (venue-owner POS) → look up User.
            //   3. meta.sold_by — whatever string the mobile app passed.
            //   4. fallback "Online" for non-POS sales.
            $teamMemberId = isset($meta['team_member_id'])
                ? (int) $meta['team_member_id']
                : null;
            $venueOwnerUserId = isset($meta['venue_owner_user_id'])
                ? (int) $meta['venue_owner_user_id']
                : null;
            $label = null;
            if ($teamMemberId && isset($teamMemberById[$teamMemberId])) {
                $tm = $teamMemberById[$teamMemberId];
                $tmName = trim((string) ($tm->name ?? ''));
                if ($tmName === '') $tmName = (string) ($tm->email ?? '');
                $label = $tmName !== '' ? $tmName : null;
            }
            if (!$label && $venueOwnerUserId && isset($userById[$venueOwnerUserId])) {
                $u = $userById[$venueOwnerUserId];
                $userName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                if ($userName === '') $userName = $u->name ?? $u->email ?? '';
                $label = $userName !== '' ? $userName : null;
            }
            if (!$label) $label = $soldBy !== '' ? $soldBy : 'Online';

            // Source identifies *where* the sale came from. This is the only
            // signal we can trust:
            //   - 'venue_owner_pos' / 'pos_app' → physical POS (cash or card)
            //   - everything else (marketplace / null) → public website sale
            //     paid via Netopia (counted as online, regardless of which
            //     card the customer used).
            $isPos = in_array($source, ['pos_app', 'venue_owner_pos'], true);

            // Payment method bucket for the staff report:
            //   - Online (public marketplace orders): always 'online'.
            //   - POS orders: distinguish cash vs card via meta or via the
            //     customer_name pattern set by the controllers
            //     ("POS Numerar" → cash, "POS Card" → card). meta.payment_method
            //     is also checked in case future POS code writes it.
            //   - POS default → 'cash' (matches venue_owner_pos controller
            //     default when no flag is passed).
            $paymentBucket = 'online';
            if ($isPos) {
                $payMeta = strtolower((string) ($meta['payment_method'] ?? ''));
                $custName = strtolower((string) ($order->customer_name ?? ''));
                if ($payMeta === 'cash' || str_contains($custName, 'numerar')) {
                    $paymentBucket = 'cash';
                } elseif (in_array($payMeta, ['card', 'tap'], true)
                    || str_contains($custName, 'card')
                ) {
                    $paymentBucket = 'card';
                } else {
                    // No explicit signal; the controller default for cash
                    // POS is cash, so attribute it there. Wrong attribution
                    // can be cleaned up later by the POS app storing
                    // meta.payment_method explicitly.
                    $paymentBucket = 'cash';
                }
            }

            $orderTicketRevenue = (float) $tickets->sum('price');

            if (!isset($buckets[$label])) {
                $buckets[$label] = [
                    'name' => $label,
                    'is_online' => !$isPos,
                    'orders' => 0,
                    'tickets' => 0,
                    'revenue' => 0.0,
                    'cash' => 0.0,
                    'card' => 0.0,
                    'online' => 0.0,
                    'ticket_types' => [],
                    'first_sale_at' => null,
                    'last_sale_at' => null,
                ];
            }
            $bucket =& $buckets[$label];
            $bucket['orders'] += 1;
            $bucket['tickets'] += $tickets->count();
            $bucket['revenue'] += $orderTicketRevenue;
            $bucket[$paymentBucket] += $orderTicketRevenue;

            $ts = $order->paid_at ?? $order->created_at;
            $tsIso = $ts?->toIso8601String();
            if ($tsIso) {
                if (!$bucket['first_sale_at'] || $tsIso < $bucket['first_sale_at']) {
                    $bucket['first_sale_at'] = $tsIso;
                }
                if (!$bucket['last_sale_at'] || $tsIso > $bucket['last_sale_at']) {
                    $bucket['last_sale_at'] = $tsIso;
                }
            }

            foreach ($tickets as $t) {
                $ttId = $t->ticket_type_id ?? 0;
                $ttName = is_array($t->ticketType?->name)
                    ? ($t->ticketType->name['ro']
                        ?? $t->ticketType->name['en']
                        ?? reset($t->ticketType->name)
                        ?? '—')
                    : ($t->ticketType?->name ?? '—');

                if (!isset($bucket['ticket_types'][$ttId])) {
                    $bucket['ticket_types'][$ttId] = [
                        'id' => $ttId,
                        'name' => $ttName,
                        'count' => 0,
                        'amount' => 0.0,
                    ];
                }
                $bucket['ticket_types'][$ttId]['count'] += 1;
                $bucket['ticket_types'][$ttId]['amount'] += (float) ($t->price ?? 0);

                if (!isset($ticketTypeTotals[$ttId])) {
                    $ticketTypeTotals[$ttId] = [
                        'id' => $ttId,
                        'name' => $ttName,
                        'count' => 0,
                        'amount' => 0.0,
                    ];
                }
                $ticketTypeTotals[$ttId]['count'] += 1;
                $ticketTypeTotals[$ttId]['amount'] += (float) ($t->price ?? 0);
            }
            unset($bucket);

            $totals['orders'] += 1;
            $totals['tickets'] += $tickets->count();
            $totals['revenue'] += $orderTicketRevenue;
            $totals[$paymentBucket] += $orderTicketRevenue;
        }

        // ── Standalone invitations (no marketplace order) ──
        // The InviteBatch flow creates Ticket rows with order_id = null for
        // every invitation. They don't fit any staff member's bucket — but
        // they should still appear in the report so totals match the events
        // dashboard. Group them by emitter: batches with
        // marketplace_organizer_id are issued by the organizer's own admin
        // ("Invitații – organizator"); batches without are issued by the
        // marketplace operator ("Invitații – admin marketplace").
        $standaloneTickets = \App\Models\Ticket::query()
            ->where('event_id', $eventId)
            ->whereNull('order_id')
            ->whereIn('status', ['valid', 'used'])
            ->where('meta->is_invitation', true)
            ->with('ticketType:id,name')
            ->get(['id', 'ticket_type_id', 'price', 'meta', 'created_at']);

        if ($standaloneTickets->isNotEmpty()) {
            $batchIds = $standaloneTickets
                ->pluck('meta.invite_batch_id')
                ->filter()
                ->unique()
                ->map(fn ($v) => (int) $v)
                ->all();
            $batchById = !empty($batchIds)
                ? \App\Models\InviteBatch::whereIn('id', $batchIds)
                    ->get(['id', 'marketplace_organizer_id'])
                    ->keyBy('id')
                : collect();

            foreach ($standaloneTickets as $t) {
                $batchId = isset($t->meta['invite_batch_id'])
                    ? (int) $t->meta['invite_batch_id']
                    : null;
                $batch = $batchId ? ($batchById[$batchId] ?? null) : null;
                $isOrganizerInvite = $batch && $batch->marketplace_organizer_id;
                $label = $isOrganizerInvite
                    ? 'Invitații – organizator'
                    : 'Invitații – admin marketplace';

                if (!isset($buckets[$label])) {
                    $buckets[$label] = [
                        'name' => $label,
                        'is_online' => false,
                        'is_invitation_bucket' => true,
                        'orders' => 0,
                        'tickets' => 0,
                        'revenue' => 0.0,
                        'cash' => 0.0,
                        'card' => 0.0,
                        'online' => 0.0,
                        'ticket_types' => [],
                        'first_sale_at' => null,
                        'last_sale_at' => null,
                    ];
                }
                $bucket =& $buckets[$label];
                $bucket['tickets'] += 1;
                // revenue stays 0 — invitations are free
                $ts = $t->created_at;
                $tsIso = $ts?->toIso8601String();
                if ($tsIso) {
                    if (!$bucket['first_sale_at'] || $tsIso < $bucket['first_sale_at']) {
                        $bucket['first_sale_at'] = $tsIso;
                    }
                    if (!$bucket['last_sale_at'] || $tsIso > $bucket['last_sale_at']) {
                        $bucket['last_sale_at'] = $tsIso;
                    }
                }

                $ttId = $t->ticket_type_id ?? 0;
                $ttName = is_array($t->ticketType?->name)
                    ? ($t->ticketType->name['ro']
                        ?? $t->ticketType->name['en']
                        ?? reset($t->ticketType->name)
                        ?? '—')
                    : ($t->ticketType?->name ?? '—');
                if (!isset($bucket['ticket_types'][$ttId])) {
                    $bucket['ticket_types'][$ttId] = [
                        'id' => $ttId,
                        'name' => $ttName,
                        'count' => 0,
                        'amount' => 0.0,
                    ];
                }
                $bucket['ticket_types'][$ttId]['count'] += 1;
                unset($bucket);
            }

            // Roll standalone-invitation ticket counts into the overall
            // totals so summary cards reflect them too. Revenue stays at
            // zero (these are free).
            $totals['tickets'] += $standaloneTickets->count();
        }

        // Convert ticket_types maps to ordered arrays
        $staff = array_map(function ($b) {
            $b['ticket_types'] = array_values($b['ticket_types']);
            usort($b['ticket_types'], fn ($a, $z) => $z['count'] <=> $a['count']);
            // Round monetary values
            foreach (['revenue', 'cash', 'card', 'online'] as $k) {
                $b[$k] = round($b[$k], 2);
            }
            foreach ($b['ticket_types'] as &$tt) {
                $tt['amount'] = round($tt['amount'], 2);
            }
            unset($tt);
            return $b;
        }, array_values($buckets));

        // Sort: highest revenue first (online bucket may or may not lead;
        // organizer wants to see top earners at a glance).
        usort($staff, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        // Rebuild ticket_types_overall directly from the tickets table so
        // it matches the marketplace dashboard's "Tip bilet" panel. The
        // order-based aggregation above misses standalone invitations
        // (created via InviteBatch without a marketplace order) and any
        // other ticket emitted outside the standard checkout path. The
        // staff-bucket breakdowns above are intentionally orders-based
        // (only orders have a staff member), but the overall lineup
        // should reflect total tickets in circulation.
        $allTickets = \App\Models\Ticket::query()
            ->where('event_id', $eventId)
            ->whereIn('status', ['valid', 'used'])
            ->with('ticketType:id,name')
            ->get(['id', 'ticket_type_id', 'price', 'status']);

        $ticketTypeTotals = [];
        foreach ($allTickets as $t) {
            $ttId = $t->ticket_type_id ?? 0;
            $ttName = is_array($t->ticketType?->name)
                ? ($t->ticketType->name['ro']
                    ?? $t->ticketType->name['en']
                    ?? reset($t->ticketType->name)
                    ?? '—')
                : ($t->ticketType?->name ?? '—');
            if (!isset($ticketTypeTotals[$ttId])) {
                $ticketTypeTotals[$ttId] = [
                    'id' => $ttId,
                    'name' => $ttName,
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }
            $ticketTypeTotals[$ttId]['count'] += 1;
            $ticketTypeTotals[$ttId]['amount'] += (float) ($t->price ?? 0);
        }
        // Backfill ticket types that have zero tickets — keeps the full
        // event lineup visible for planning.
        foreach ($event->ticketTypes as $tt) {
            if (isset($ticketTypeTotals[$tt->id])) continue;
            $name = is_array($tt->name)
                ? ($tt->name['ro'] ?? $tt->name['en'] ?? reset($tt->name) ?? '—')
                : ($tt->name ?? '—');
            $ticketTypeTotals[$tt->id] = [
                'id' => $tt->id,
                'name' => $name,
                'count' => 0,
                'amount' => 0.0,
            ];
        }

        $ticketTypeTotals = array_values($ticketTypeTotals);
        usort($ticketTypeTotals, fn ($a, $b) => $b['count'] <=> $a['count']);
        foreach ($ticketTypeTotals as &$tt) {
            $tt['amount'] = round($tt['amount'], 2);
        }
        unset($tt);

        foreach (['revenue', 'cash', 'card', 'online'] as $k) {
            $totals[$k] = round($totals[$k], 2);
        }

        // Check-in attribution per operator. tickets.checked_in_by stores
        // the operator's NAME as free text (e.g. "Ionescu-Posea Doina"),
        // not a User FK — legacy scan endpoints write the scanner's display
        // name directly. Old historic scans have checked_in_at populated
        // but checked_in_by NULL (legacy code paths that never captured
        // the operator); those collapse into a single "Operator necunoscut"
        // bucket so the organizer still sees the total scan count without
        // an N+1 of anonymous lines.
        $scannedTickets = \App\Models\Ticket::query()
            ->where('event_id', $eventId)
            ->whereNotNull('checked_in_at')
            ->get(['id', 'ticket_type_id', 'checked_in_at', 'checked_in_by', 'checked_in_via']);

        $scanBuckets = []; // keyed by lowercased name or '__unknown__'
        $scanViaTotals = [];
        $scanTotal = 0;

        foreach ($scannedTickets as $t) {
            $scanTotal++;
            $via = $t->checked_in_via ?: 'unknown';
            $scanViaTotals[$via] = ($scanViaTotals[$via] ?? 0) + 1;

            $rawName = trim((string) ($t->checked_in_by ?? ''));
            // Defensive: if a future migration ever swaps the column to an
            // FK, a numeric string here would still produce a stable
            // grouping key. We never try to resolve it against users —
            // historically the value IS the display name.
            $key = $rawName !== '' ? 'n:' . mb_strtolower($rawName) : '__unknown__';

            if (!isset($scanBuckets[$key])) {
                $scanBuckets[$key] = [
                    'name' => $rawName !== '' ? $rawName : 'Operator necunoscut',
                    'is_unknown' => $rawName === '',
                    'scans' => 0,
                    'via' => [],
                    'first_scan_at' => null,
                    'last_scan_at' => null,
                ];
            }
            $scanBuckets[$key]['scans']++;
            if ($via !== 'unknown') {
                $scanBuckets[$key]['via'][$via] = ($scanBuckets[$key]['via'][$via] ?? 0) + 1;
            }
            $tsIso = $t->checked_in_at?->toIso8601String();
            if ($tsIso) {
                if (!$scanBuckets[$key]['first_scan_at'] || $tsIso < $scanBuckets[$key]['first_scan_at']) {
                    $scanBuckets[$key]['first_scan_at'] = $tsIso;
                }
                if (!$scanBuckets[$key]['last_scan_at'] || $tsIso > $scanBuckets[$key]['last_scan_at']) {
                    $scanBuckets[$key]['last_scan_at'] = $tsIso;
                }
            }
        }

        $scanners = array_values($scanBuckets);
        // Sort: known operators first (DESC by scans), unknown bucket last
        // so it doesn't dominate the top of the table even when it carries
        // the bulk of legacy scans.
        usort($scanners, function ($a, $b) {
            if ($a['is_unknown'] !== $b['is_unknown']) {
                return $a['is_unknown'] ? 1 : -1;
            }
            return $b['scans'] <=> $a['scans'];
        });

        $title = is_array($event->title)
            ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title))
            : $event->title;

        return $this->success([
            'event' => [
                'id' => $event->id,
                'title' => $title,
                'date' => $event->event_date?->format('Y-m-d')
                    ?? $event->range_start_date?->format('Y-m-d'),
                'currency' => 'RON',
            ],
            'totals' => $totals,
            'ticket_types_overall' => $ticketTypeTotals,
            'staff' => $staff,
            'scans' => [
                'total' => $scanTotal,
                'by_via' => $scanViaTotals,
                'operators' => $scanners,
            ],
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
            // is_active is true for active artists, but legacy/imported rows
            // often have NULL — accept either so they're searchable.
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            });

        if (strlen($search) >= 2) {
            // PostgreSQL LIKE is case-sensitive and accent-sensitive; use the
            // unaccent() extension + LOWER() so "ana", "ANA", and "Ană" all
            // match the same artist.
            $term = '%' . mb_strtolower($search) . '%';
            $query->whereRaw('unaccent(LOWER(name)) LIKE unaccent(?)', [$term]);
        }

        $artists = $query->orderBy('name')
            ->limit(100)
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

        $baseSlug = \Illuminate\Support\Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (\App\Models\Artist::where('slug', $slug)->exists()) {
            $i++;
            $slug = $baseSlug . '-' . $i;
        }

        $artist = \App\Models\Artist::create([
            'name' => $validated['name'],
            'slug' => $slug,
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
            // Rejected by admin — organizer can edit and resubmit.
            if ($event->rejected_at) {
                return 'rejected';
            }
            // Submitted, waiting for approval.
            if ($event->submitted_at) {
                return 'pending_review';
            }
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
            return $this->buildNaiveIso($date->format('Y-m-d'), $time);
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
            return $this->buildNaiveIso($endDate->format('Y-m-d'), $endTime);
        }
        return null;
    }

    /**
     * Get doors_open_at datetime from Event model
     */
    protected function getDoorsAt(Event $event): ?string
    {
        if ($event->event_date && $event->door_time) {
            return $this->buildNaiveIso($event->event_date->format('Y-m-d'), $event->door_time);
        }
        return null;
    }

    /**
     * Build a "naïve" ISO datetime string (no Z, no offset). JS Date parses
     * this in the browser's local timezone, so the date the admin entered
     * (e.g. May 1 22:30) is rendered exactly as entered, regardless of the
     * server's app.timezone (UTC).
     *
     * Carbon::parse('2026-05-01 22:30')->toIso8601String() would produce
     * '2026-05-01T22:30:00+00:00', and a browser in UTC+3 would shift it
     * to May 2 01:30 → wrong day shown.
     */
    protected function buildNaiveIso(string $date, ?string $time): string
    {
        $time = $time ?: '00:00';
        // Normalize HH:MM → HH:MM:00 so the browser parses it consistently.
        if (substr_count($time, ':') === 1) {
            $time .= ':00';
        }
        return $date . 'T' . $time;
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

        // Mobile categorization helper. The mobile app's categorizeEvent() only
        // looks at `event_date || starts_at`, so a multi-day/range event whose
        // start is in the past gets mistakenly marked 'past' even while still
        // running (and the scanner shows "Eveniment s-a încheiat"). When today
        // is inside [start_date .. end_date], expose `event_date` = NOW so the
        // app categorizes it as live/today and check-in stays available.
        $eventDateForMobile = null;
        $sd = $event->start_date;
        $ed = $event->end_date ?? $sd;
        if ($sd && $ed && !$event->is_cancelled && !$event->is_postponed) {
            $rangeStart = Carbon::parse($sd->format('Y-m-d') . ' 00:00:00');
            $rangeEnd = Carbon::parse($ed->format('Y-m-d') . ' 23:59:59');
            $now = Carbon::now();
            if ($now->greaterThanOrEqualTo($rangeStart)
                && $now->lessThanOrEqualTo($rangeEnd)
                && !$now->isSameDay($sd)) {
                $eventDateForMobile = Carbon::now()->format('Y-m-d\TH:i:s');
            }
        }

        return [
            'id' => $event->id,
            'name' => $this->getLocalizedTitle($event),
            'slug' => $event->slug,
            'starts_at' => $this->getStartsAt($event),
            'ends_at' => $this->getEndsAt($event),
            'event_date' => $eventDateForMobile,
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
            'display_template' => $event->display_template ?? 'standard',
            'venue_config' => is_array($event->venue_config) ? $event->venue_config : (object) [],
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
            'thank_you_message' => $this->getLocalizedField($event, 'thank_you_message'),
            'short_description' => $this->getLocalizedField($event, 'short_description'),
            'starts_at' => $this->getStartsAt($event),
            'ends_at' => $this->getEndsAt($event),
            'doors_open_at' => $this->getDoorsAt($event),
            'venue_id' => $event->venue_id,
            'venue_name' => $venueName,
            'venue_address' => $event->venue?->address ?? $event->address,
            'venue_city' => $event->venue?->city ?? $event->marketplaceCity?->name ?? null,
            // Event web presence. The schema has THREE columns:
            //   - website_url        = venue website (auto-filled when a venue is
            //                          selected in admin; legacy field organizer
            //                          previously wrote into for "Website eveniment")
            //   - event_website_url  = the event's own website (admin label
            //                          "Website Eveniment")
            //   - facebook_url       = the event's Facebook URL
            // Organizer JS prefers event_website_url and falls back to
            // website_url so legacy data still shows up.
            'website_url' => $event->website_url,
            'event_website_url' => $event->event_website_url,
            'facebook_url' => $event->facebook_url,
            'video_url' => $event->video_url,
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
            'rejection_reason' => $event->rejection_reason,
            'rejected_at' => $event->rejected_at?->toIso8601String(),
            'tickets_sold' => $event->total_tickets_sold,
            'revenue' => (float) $event->total_revenue,
            'views' => $event->views_count ?? 0,
            // PERF P2/8 — fetch the per-ticket-type stats hash ONCE outside
            // the loop instead of running 2 COUNTs per ticket type per
            // render. For a 5-type event this collapses 10 queries to 0
            // (cache hit) or 2 (cache miss — GROUP BY queries inside the
            // service compute step). See EventStatsCache::compute().
            'ticket_types' => (function () use ($event) {
                $stats = \App\Services\EventStatsCache::get($event->id);
                $perType = $stats['per_ticket_type'] ?? [];
                return $event->ticketTypes->map(function ($tt) use ($event, $perType) {
                $validTickets = (int) ($perType[$tt->id]['sold'] ?? 0);
                $checkedIn = (int) ($perType[$tt->id]['checked_in'] ?? 0);

                // Available count — for seated events, try to count real
                // event_seats with status='available' that map to this
                // ticket type (via the row / section pivots). On ANY
                // error, fall back to the simple quota - sold math so a
                // broken seat count never blanks the ticket list.
                $hasSeats = (bool) $tt->hasSeatingAssigned();
                $defaultAvailable = $tt->quota_total ? max(0, $tt->quota_total - $validTickets) : 0;
                $available = $defaultAvailable;
                if ($hasSeats && $event->seating_layout_id) {
                    try {
                        $available = $this->countAvailableSeatsForTicketType($tt, $event);
                    } catch (\Throwable $e) {
                        \Log::warning('countAvailableSeatsForTicketType failed', [
                            'ticket_type_id' => $tt->id,
                            'event_id' => $event->id,
                            'error' => $e->getMessage(),
                        ]);
                        $available = $defaultAvailable;
                    }
                }

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
                    'has_seats' => $hasSeats,
                    'color' => $tt->color ?? null,
                    'checked_in' => $checkedIn,
                ];
                });
            })(),
            'has_seating' => (bool) $event->seating_layout_id,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * Count available seats (status='available') in event_seats for the
     * latest published event seating layout, filtered to seats whose
     * (section_code, row_label) matches one of the ticket type's row or
     * section assignments. Returns 0 when nothing maps. Used by the
     * mobile SalesScreen to show "X locuri disponibile" per seated
     * ticket type without making a second seating-map round-trip.
     */
    protected function countAvailableSeatsForTicketType(TicketType $tt, Event $event): int
    {
        $layout = \App\Models\Seating\EventSeatingLayout::where('event_id', $event->id)
            ->published()
            ->latest('published_at')
            ->first();

        if (!$layout) {
            return 0;
        }

        $sectionNames = $tt->seatingSections()->pluck('name')->all();
        $rowPairs = $tt->seatingRows()
            ->with('section:id,name')
            ->get()
            ->filter(fn ($r) => $r->section)
            ->map(fn ($r) => [$r->section->name, $r->label])
            ->all();

        if (empty($sectionNames) && empty($rowPairs)) {
            return 0;
        }

        // event_seats column is `section_name` (not `section_code`).
        // Build the predicate manually so the first clause is a regular
        // where (not orWhere) — Laravel's grammar emits cleaner SQL.
        $query = \App\Models\Seating\EventSeat::where('event_seating_id', $layout->id)
            ->where('status', 'available')
            ->where(function ($q) use ($sectionNames, $rowPairs) {
                $first = true;
                if (!empty($sectionNames)) {
                    $q->whereIn('section_name', $sectionNames);
                    $first = false;
                }
                foreach ($rowPairs as [$secName, $rowLabel]) {
                    $clause = function ($qq) use ($secName, $rowLabel) {
                        $qq->where('section_name', $secName)
                           ->where('row_label', $rowLabel);
                    };
                    if ($first) {
                        $q->where($clause);
                        $first = false;
                    } else {
                        $q->orWhere($clause);
                    }
                }
            });

        return (int) $query->count();
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
        $schema = \Illuminate\Support\Facades\Schema::class;
        if (!$schema::hasTable('venue_gates')) {
            $schema::create('venue_gates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
                $table->foreignId('marketplace_organizer_id')->nullable()->constrained('marketplace_organizers')->nullOnDelete();
                $table->string('name');
                $table->string('type')->default('entry');
                $table->string('location')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['venue_id', 'type']);
                $table->index(['venue_id', 'marketplace_organizer_id']);
            });
            return;
        }
        // Self-heal: pe prod migratia 2026_06_03_120000 poate sa nu fi rulat.
        // Adauga coloana + index daca lipsesc; altfel operatiile de create/list
        // filtreaza pe o coloana inexistenta si erorile 500 sunt silentioase.
        if (!$schema::hasColumn('venue_gates', 'marketplace_organizer_id')) {
            $schema::table('venue_gates', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->foreignId('marketplace_organizer_id')
                    ->nullable()
                    ->after('venue_id')
                    ->constrained('marketplace_organizers')
                    ->nullOnDelete();
                $table->index(['venue_id', 'marketplace_organizer_id']);
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
        // Filtrare: doar portile organizatorului curent SAU legacy NULL
        // (create inainte de migratia 2026_06_03_120000 care a adaugat coloana).
        // Motivatie: pe un venue partajat de mai multi organizatori, backfill-ul
        // 2026_06_10 populeaza doar single-owner venues. Restul raman NULL si
        // aparent 'invizibile' pentru toti — user-ul semnalase ca vede portile
        // altor organizatori (in realitate erau portile legacy NULL). Cu filtru
        // OR NULL, fiecare organizator vede propriile porti + legacy shared.
        // Bug precedent: unele porti nou create nu apareau in lista pentru ca
        // filtrul strict '=$organizer->id' excludea toate valorile != org-ul
        // curent, inclusiv unele salvate cu id-uri inconsistente (ex. proxy
        // admin acting on behalf) — acum cade in categoria propriu-organizer.
        $gates = $venue->gates()
            ->where(function ($q) use ($organizer) {
                $q->where('marketplace_organizer_id', $organizer->id)
                  ->orWhereNull('marketplace_organizer_id');
            })
            ->orderBy('sort_order')
            ->get();

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
            // Bug-fix: salveaza organizer-ul care creeaza poarta, ca sa apara
            // doar pentru el la list (cand mai mulți organizatori folosesc
            // acelasi venue fizic).
            'marketplace_organizer_id' => $organizer->id,
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
        // Bug-fix: blocheaza modificarea portilor altor organizatori care folosesc
        // acelasi venue. Portile legacy (organizer_id=NULL) raman editabile by all.
        if ($gate->marketplace_organizer_id && $gate->marketplace_organizer_id !== $organizer->id) {
            return $this->error('Această poartă aparține altui organizator.', 403);
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
        // Bug-fix: nu permite stergerea portilor altor organizatori care folosesc
        // acelasi venue. Portile legacy (organizer_id=NULL) raman deletable.
        if ($gate->marketplace_organizer_id && $gate->marketplace_organizer_id !== $organizer->id) {
            return $this->error('Această poartă aparține altui organizator.', 403);
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
