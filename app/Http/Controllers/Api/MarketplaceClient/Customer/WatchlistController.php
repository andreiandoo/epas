<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WatchlistController extends BaseController
{
    /**
     * Get customer's watchlist
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $query = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 12), 50);
        $watchlist = $query->paginate($perPage);

        // Get event details
        $eventIds = collect($watchlist->items())->pluck('marketplace_event_id')->unique();
        $events = DB::table('marketplace_events')
            ->whereIn('id', $eventIds)
            ->get()
            ->keyBy('id');

        $formattedItems = collect($watchlist->items())->map(function ($item) use ($events) {
            $event = $events->get($item->marketplace_event_id);
            if (!$event) {
                return null;
            }

            $startsAt = \Carbon\Carbon::parse($event->starts_at);
            $isUpcoming = $startsAt >= now();
            $daysUntil = $isUpcoming ? now()->diffInDays($startsAt, false) : null;

            return [
                'id' => $item->id,
                'added_at' => $item->created_at,
                'notify_on_sale' => (bool) ($item->notify_on_sale ?? true),
                'notify_on_price_drop' => (bool) ($item->notify_on_price_drop ?? false),
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'date' => $event->starts_at,
                    'date_formatted' => $startsAt->format('d M Y'),
                    'time' => $startsAt->format('H:i'),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                    'image' => $event->image,
                    'min_price' => $event->min_price,
                    'currency' => $event->currency ?? 'RON',
                    'is_upcoming' => $isUpcoming,
                    'days_until' => $daysUntil,
                    'is_sold_out' => (bool) ($event->is_sold_out ?? false),
                    'status' => $event->status ?? 'active',
                ],
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $formattedItems,
            'meta' => [
                'current_page' => $watchlist->currentPage(),
                'last_page' => $watchlist->lastPage(),
                'per_page' => $watchlist->perPage(),
                'total' => $watchlist->total(),
            ],
        ]);
    }

    /**
     * Add event to watchlist
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'notify_on_sale' => 'boolean',
            'notify_on_price_drop' => 'boolean',
        ]);

        // Check if event exists
        $event = DB::table('marketplace_events')
            ->where('id', $validated['event_id'])
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Check if already in watchlist
        $existing = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_event_id', $validated['event_id'])
            ->first();

        if ($existing) {
            return $this->error('Event is already in your watchlist', 422);
        }

        // Add to watchlist
        $id = DB::table('marketplace_customer_watchlist')->insertGetId([
            'marketplace_client_id' => $client->id,
            'marketplace_customer_id' => $customer->id,
            'marketplace_event_id' => $validated['event_id'],
            'notify_on_sale' => $validated['notify_on_sale'] ?? true,
            'notify_on_price_drop' => $validated['notify_on_price_drop'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->success([
            'watchlist_id' => $id,
        ], 'Event added to watchlist', 201);
    }

    /**
     * Update watchlist item preferences
     */
    public function update(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'notify_on_sale' => 'boolean',
            'notify_on_price_drop' => 'boolean',
        ]);

        $updated = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_event_id', $eventId)
            ->update([
                'notify_on_sale' => $validated['notify_on_sale'] ?? true,
                'notify_on_price_drop' => $validated['notify_on_price_drop'] ?? false,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return $this->error('Event not found in watchlist', 404);
        }

        return $this->success(null, 'Watchlist preferences updated');
    }

    /**
     * Remove event from watchlist
     */
    public function destroy(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $deleted = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_event_id', $eventId)
            ->delete();

        if (!$deleted) {
            return $this->error('Event not found in watchlist', 404);
        }

        return $this->success(null, 'Event removed from watchlist');
    }

    /**
     * Check if event is in watchlist
     */
    public function check(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $exists = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_event_id', $eventId)
            ->exists();

        return $this->success([
            'in_watchlist' => $exists,
        ]);
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
