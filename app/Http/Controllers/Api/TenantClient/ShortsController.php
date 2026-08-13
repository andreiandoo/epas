<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortPayload;
use App\Services\Shorts\ShortPromotionService;
use App\Services\Shorts\ShortReminderService;
use App\Services\Shorts\ShortTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public read surface for the mobile shorts feed.
 *
 * Reads are open (the app browses before login); when a customer token is
 * present the payload is enriched with the viewer's like/save state.
 */
class ShortsController extends Controller
{
    public function __construct(
        private readonly ShortFeedService $feed,
        private readonly ShortPayload $payload,
        private readonly ShortTelemetryService $telemetry,
    ) {}

    /**
     * GET tenant-client/shorts?feed=for_you|featured|nearby&cursor=...
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feed' => ['nullable', 'string', 'in:'.implode(',', ShortFeedService::FEEDS)],
            'cursor' => ['nullable', 'string', 'max:1024'], // poarta si id-urile deja servite (ShortFeedCursor)
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
            'event_id' => ['nullable', 'integer'],
            'language' => ['nullable', 'string', 'max:8'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:2'],
            // Anonymous viewers are the bulk of this surface; without a stable
            // per-device id an ad campaign cannot be frequency-capped for them.
            'session_id' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->feed->page(
            feed: $validated['feed'] ?? 'for_you',
            cursor: $validated['cursor'] ?? null,
            limit: (int) ($validated['limit'] ?? config('shorts.feed.page_size', 10)),
            customer: $this->viewer($request),
            filters: array_filter([
                'event_id' => $validated['event_id'] ?? null,
                'language' => $validated['language'] ?? null,
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'] ?? null,
                'session_id' => $validated['session_id'] ?? null,
                'marketplace_client_id' => $this->marketplaceClientId($request),
            ]),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET tenant-client/shorts/{short} — deep-link / share target.
     */
    public function show(Request $request, int $short): JsonResponse
    {
        $model = Short::query()->published()->with(['owner', 'event'])->find($short);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Short not found'], 404);
        }

        $customer = $this->viewer($request);

        return response()->json([
            'success' => true,
            'data' => $this->payload->one(
                $model,
                liked: $customer ? $model->likeRecords()->where('marketplace_customer_id', $customer->id)->exists() : false,
                saved: $customer ? $model->saveRecords()->where('marketplace_customer_id', $customer->id)->exists() : false,
                // The feed batches this lookup across a page; a single short
                // opened from a deep link has no page to batch with, and without
                // it the shared link is the one place that always offered a
                // reminder the viewer had already set (D2).
                reminded: $customer
                    ? app(ShortReminderService::class)->isReminded($model, $customer)
                    : false,
            ),
        ]);
    }

    /**
     * GET tenant-client/events/{slug}/shorts
     */
    public function forEvent(Request $request, string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->feed->forOwner(
                owner: $event,
                cursor: $request->query('cursor'),
                limit: (int) $request->query('limit', config('shorts.feed.page_size', 10)),
                customer: $this->viewer($request),
            ),
        ]);
    }

    /**
     * GET tenant-client/artists/{slug}/shorts
     */
    public function forArtist(Request $request, string $slug): JsonResponse
    {
        /* Dupa SLUG sau dupa ID: fisa artistului se deschide si dintr-un card
           care poarta id-ul numeric, nu doar dintr-un link cu slug. Cautarea
           doar pe slug intorcea 404 exact pentru drumul cel mai des folosit. */
        $artist = Artist::query()
            ->when(ctype_digit($slug), fn ($q) => $q->where('id', (int) $slug), fn ($q) => $q->where('slug', $slug))
            ->first();

        if (! $artist) {
            return response()->json(['success' => false, 'message' => 'Artist not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->feed->forOwner(
                owner: $artist,
                cursor: $request->query('cursor'),
                limit: (int) $request->query('limit', config('shorts.feed.page_size', 10)),
                customer: $this->viewer($request),
            ),
        ]);
    }

    /**
     * POST tenant-client/shorts/{short}/cta-click
     *
     * Separate from the batched telemetry: a CTA click is the last thing the
     * client does before leaving the feed for checkout, so it must not sit in a
     * queue that flushes five seconds later — by then the screen is gone.
     * Public, because the tap happens before login just as often as after.
     */
    public function ctaClick(Request $request, int $short): JsonResponse
    {
        $model = Short::query()->published()->find($short);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Short not found'], 404);
        }

        $customer = $this->viewer($request);

        ShortEvent::create([
            'short_id' => $model->id,
            'marketplace_customer_id' => $customer?->id,
            'session_id' => substr((string) $request->input('session_id'), 0, 64) ?: null,
            'type' => ShortEvent::TYPE_CTA_CLICK,
            'feed' => $request->input('feed'),
            'created_at' => now(),
        ]);

        $model->newQuery()->whereKey($model->id)->increment('cta_clicks');

        // CPC billing. The promotion id is the one the feed handed the client
        // with this item — a short reached organically carries none, and an id
        // that does not belong to this short charges nothing.
        $promotion = app(ShortPromotionService::class)->chargeClickFor(
            $model,
            $request->integer('promotion_id') ?: null,
            $customer,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'short_id' => $model->id,
                'promotion_id' => $promotion?->id,
                // Everything checkout needs to honour the short's offer.
                'checkout' => [
                    'event_id' => $model->event_id,
                    'ticket_type_id' => $model->cta_ticket_type_id,
                    'promo_code' => $model->promo_code,
                    'source_short_id' => $model->id,
                    'source_feed' => $request->input('feed'),
                ],
            ],
        ]);
    }

    /**
     * POST tenant-client/shorts/events — batched, fire-and-forget telemetry.
     *
     * Guests are accepted too (identified only by session_id) because the feed
     * is browsable before login.
     */
    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'string', 'max:64'],
            'events' => ['required', 'array', 'min:1', 'max:'.config('shorts.telemetry.max_batch', 100)],
            'events.*.short_id' => ['required', 'integer'],
            'events.*.type' => ['required', 'string', 'max:32'],
            'events.*.watch_ms' => ['nullable', 'integer', 'min:0'],
            'events.*.watch_ratio' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.feed' => ['nullable', 'string', 'max:32'],
            'events.*.meta' => ['nullable', 'array'],
        ]);

        $result = $this->telemetry->record(
            events: $validated['events'],
            customer: $this->viewer($request),
            sessionId: $validated['session_id'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $result], 202);
    }

    /**
     * Resolve an optional marketplace customer from the bearer token. Missing or
     * invalid tokens simply mean "guest" — never an error on a public read.
     */
    protected function viewer(Request $request): ?MarketplaceCustomer
    {
        $user = $request->user();

        if ($user instanceof MarketplaceCustomer) {
            return $user;
        }

        $user = $request->user('sanctum');

        return $user instanceof MarketplaceCustomer ? $user : null;
    }

    protected function marketplaceClientId(Request $request): ?int
    {
        $client = $request->attributes->get('marketplace_client');

        return $client?->id;
    }
}
