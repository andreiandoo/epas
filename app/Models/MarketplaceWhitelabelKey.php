<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API key of one organizer's whitelabel site.
 *
 * Unlike the marketplace API key it only opens the endpoints such a site
 * needs, and only for the organizer's own events — see scopeViolation().
 */
class MarketplaceWhitelabelKey extends Model
{
    public const PREFIX = 'wlk_';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'key_hash',
        'secret',
        'last_used_at',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['secret', 'key_hash'];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public static function isWhitelabelKey(?string $apiKey): bool
    {
        return is_string($apiKey) && str_starts_with($apiKey, self::PREFIX);
    }

    /** Find the key row for a key received on a request. */
    public static function resolve(string $plainKey): ?self
    {
        return static::where('key_hash', hash('sha256', $plainKey))->first();
    }

    /** The organizer's key, created on first use. */
    public static function forOrganizer(MarketplaceOrganizer $organizer): self
    {
        return static::where('marketplace_organizer_id', $organizer->id)->first()
            ?? static::issue($organizer);
    }

    /** Replace the organizer's key. The previous one stops working at once. */
    public static function regenerate(MarketplaceOrganizer $organizer): self
    {
        return static::issue($organizer);
    }

    protected static function issue(MarketplaceOrganizer $organizer): self
    {
        $plainKey = self::PREFIX . Str::random(56);

        return static::updateOrCreate(
            ['marketplace_organizer_id' => $organizer->id],
            [
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'key_hash' => hash('sha256', $plainKey),
                'secret' => $plainKey,
                'last_used_at' => null,
            ]
        );
    }

    /**
     * What a whitelabel key may NOT do with this request.
     *
     * Returns null when the request is allowed, otherwise a short reason.
     * Anything that is not explicitly listed here is refused.
     */
    public function scopeViolation(Request $request): ?string
    {
        $path = preg_replace('#^api/#', '', trim($request->path(), '/'));
        $method = $request->method();

        // Analytics bridge (page view / add to cart / purchase)
        if (preg_match('#^marketplace-tracking/(track|batch|pixel)$#', $path)) {
            return null;
        }

        if (!str_starts_with($path, 'marketplace-client/')) {
            return 'endpoint_not_allowed';
        }
        $path = substr($path, strlen('marketplace-client/'));

        // Organizer page: own profile + own upcoming events
        if ($method === 'GET' && preg_match('#^marketplace-events/organizers/([^/]+)$#', $path, $m)) {
            $identifier = rawurldecode($m[1]);
            $organizer = $this->organizer;

            return $organizer && ($identifier === (string) $organizer->id || $identifier === $organizer->slug)
                ? null
                : 'other_organizer';
        }

        // Event page (also the seat map + live seat statuses)
        if ($method === 'GET' && preg_match('#^marketplace-events/([^/]+)$#', $path, $m)) {
            $identifier = rawurldecode($m[1]);
            $event = Event::where('marketplace_client_id', $this->marketplace_client_id)
                ->where(is_numeric($identifier) ? 'id' : 'slug', $identifier)
                ->first(['id', 'marketplace_organizer_id']);

            if (!$event) {
                // Bots probing the site's /{slug} catch-all (e.g. /info)
                return 'event_not_found';
            }

            return (int) $event->marketplace_organizer_id === (int) $this->marketplace_organizer_id
                ? null
                : 'event_not_owned';
        }

        if ($method === 'POST' && $path === 'promo-codes/validate') {
            $eventIds = $this->eventIdsFromItems((array) $request->input('items', []));
            if ($request->filled('event_id')) {
                $eventIds[] = (int) $request->input('event_id');
            }

            return $this->ownsEvents($eventIds) ? null : 'event_not_owned';
        }

        // Checkout: every line must be a ticket for one of the organizer's
        // events. No items would fall back to the server-side cart, which is
        // not checked here, so that is refused as well.
        if ($method === 'POST' && $path === 'customer/checkout') {
            $items = (array) $request->input('items', []);
            $eventIds = $this->eventIdsFromItems($items);
            if (empty($items) || count($eventIds) !== count($items)) {
                return 'checkout_items_not_allowed';
            }

            return $this->ownsEvents($eventIds) ? null : 'event_not_owned';
        }

        // Seat holds
        if ($method === 'POST' && $path === 'customer/cart/items/with-seats') {
            return $this->ownsEvents([(int) $request->input('event_id')]) ? null : 'event_not_owned';
        }
        if ($method === 'DELETE' && ($path === 'customer/cart/seats' || preg_match('#^customer/cart/items/\d+_\d+$#', $path))) {
            return null;
        }

        // Event sync for the organizer's own site. Read only, the controller
        // limits it to this key's organizer.
        if ($method === 'GET' && preg_match('#^whitelabel/sync/events(/\d+)?$#', $path)) {
            return null;
        }

        // Order lookup on the thank-you page (purchase tracking)
        if ($method === 'GET' && preg_match('#^orders/([^/]+)$#', $path, $m)) {
            $identifier = rawurldecode($m[1]);
            $order = Order::where('marketplace_client_id', $this->marketplace_client_id)
                ->where(is_numeric($identifier) ? 'id' : 'order_number', $identifier)
                ->first(['id', 'marketplace_organizer_id', 'event_id']);
            if (!$order) {
                return 'order_not_owned';
            }
            $organizerId = $order->marketplace_organizer_id
                ?? Event::where('id', $order->event_id)->value('marketplace_organizer_id');

            return (int) $organizerId === (int) $this->marketplace_organizer_id ? null : 'order_not_owned';
        }

        return 'endpoint_not_allowed';
    }

    /**
     * Event ids referenced by cart/checkout lines. A line without an event
     * (e.g. an activity booking) yields no id, so the caller can spot it.
     *
     * @return array<int, int>
     */
    protected function eventIdsFromItems(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $eventId = $item['event']['id'] ?? $item['event_id'] ?? null;
            if (is_numeric($eventId) && (int) $eventId > 0) {
                $ids[] = (int) $eventId;
            }
        }

        return $ids;
    }

    /** True when every id is an event of this key's organizer (and there is at least one). */
    protected function ownsEvents(array $eventIds): bool
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        if (empty($eventIds)) {
            return false;
        }

        return Event::where('marketplace_client_id', $this->marketplace_client_id)
            ->where('marketplace_organizer_id', $this->marketplace_organizer_id)
            ->whereIn('id', $eventIds)
            ->count() === count($eventIds);
    }
}
