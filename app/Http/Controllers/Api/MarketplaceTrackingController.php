<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceClient;
use App\Services\Analytics\RedisAnalyticsService;
use App\Services\GeoIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketplaceTrackingController extends Controller
{
    protected RedisAnalyticsService $redisAnalytics;
    protected ?string $userAgent = null;

    public function __construct(RedisAnalyticsService $redisAnalytics)
    {
        $this->redisAnalytics = $redisAnalytics;
    }

    /**
     * Track an event (page view, add to cart, purchase, etc.)
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|max:50',
            'marketplace_event_id' => 'nullable|integer',
            'marketplace_client_id' => 'nullable|integer',
            // Channel identifies the traffic source funnel: marketplace (ambilet/bilete.online),
            // whitelabel (organizer's ZIP-packaged site), or embed_widget (third-party iframe).
            // Defaults to 'marketplace' below so legacy callers don't need to change.
            'channel' => 'nullable|string|in:marketplace,whitelabel,embed_widget',
            'visitor_id' => 'nullable|string|max:64',
            'session_id' => 'nullable|string|max:64',
            'page_url' => 'nullable|string|max:2000',
            'page_path' => 'nullable|string|max:500',
            'page_title' => 'nullable|string|max:255',
            'content_id' => 'nullable|string|max:100',
            'content_type' => 'nullable|string|max:50',
            'content_name' => 'nullable|string|max:255',
            'event_value' => 'nullable|numeric',
            'quantity' => 'nullable|integer',
            'currency' => 'nullable|string|max:3',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
            'gclid' => 'nullable|string|max:255',
            'fbclid' => 'nullable|string|max:255',
            'ttclid' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:2000',
            // Facebook CAPI extras (Etapa 4 — Layer B)
            'fbp' => 'nullable|string|max:128',
            'fbc' => 'nullable|string|max:512',
            'client_event_id' => 'nullable|string|max:128',
            'email' => 'nullable|email|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            // Order linkage — frontend passes this on purchase events so
            // ROAS attribution can join core_customer_events ↔ orders.
            'order_id' => 'nullable|integer',
        ]);

        // Generate or use provided visitor/session IDs
        $visitorId = $request->input('visitor_id') ?: $this->generateVisitorId($request);
        $rawSessionId = $request->input('session_id');
        $sessionId = ($rawSessionId && Str::isUuid($rawSessionId)) ? $rawSessionId : Str::uuid()->toString();

        // Parse user agent
        $this->userAgent = $request->userAgent() ?? '';
        $uaParsed = $this->parseUserAgent($this->userAgent);

        // Get location from IP (uses multi-provider fallback: ipgeolocation.io -> ip-api.com -> ipwhois.io)
        $geoIpService = app(GeoIpService::class);
        $location = $geoIpService->getLocation($request->ip());

        // Determine event category
        $eventCategory = $this->determineEventCategory($request->input('event_type'));

        // Resolve marketplace client ID from authenticated client (middleware) or request body
        $authenticatedClient = $request->attributes->get('marketplace_client');
        $clientId = $request->input('marketplace_client_id') ?? $authenticatedClient?->id;

        // Truncate string fields to fit varchar(255) — fbclid, referrer, page_url can exceed 255 chars
        $t = fn (?string $v, int $max = 255) => $v ? mb_substr($v, 0, $max) : $v;

        // Resolve order_id: prefer explicit payload, fall back to content_id
        // for purchase events (legacy frontends only sent content_id).
        $resolvedOrderId = $request->input('order_id');
        if (!$resolvedOrderId
            && $request->input('event_type') === 'purchase'
            && ctype_digit((string) $request->input('content_id'))
        ) {
            $resolvedOrderId = (int) $request->input('content_id');
        }

        // Create the tracking event
        $event = CoreCustomerEvent::create([
            'marketplace_event_id' => $request->input('marketplace_event_id'),
            'marketplace_client_id' => $clientId,
            'channel' => $request->input('channel', 'marketplace'),
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'event_type' => $t($request->input('event_type')),
            'order_id' => $resolvedOrderId,
            'event_category' => $t($eventCategory),
            'event_action' => $t($request->input('event_action')),
            'event_label' => $t($request->input('event_label')),
            'event_value' => $request->input('event_value'),
            'page_url' => $t($request->input('page_url'), 2048),
            'page_path' => $t($request->input('page_path'), 500),
            'page_title' => $t($request->input('page_title')),
            'content_id' => $t($request->input('content_id')),
            'content_type' => $t($request->input('content_type')),
            'content_name' => $t($request->input('content_name')),
            'quantity' => $request->input('quantity'),
            'currency' => $t($request->input('currency', 'RON'), 10),
            'referrer' => $t($request->input('referrer'), 2048),
            'utm_source' => $t($request->input('utm_source')),
            'utm_medium' => $t($request->input('utm_medium')),
            'utm_campaign' => $t($request->input('utm_campaign')),
            'utm_term' => $t($request->input('utm_term')),
            'utm_content' => $t($request->input('utm_content')),
            'gclid' => $t($request->input('gclid')),
            'fbclid' => $t($request->input('fbclid')),
            'ttclid' => $t($request->input('ttclid')),
            'device_type' => $uaParsed['device_type'],
            'device_brand' => $uaParsed['device_brand'],
            'browser' => $uaParsed['browser'],
            'browser_version' => $uaParsed['browser_version'],
            'os' => $uaParsed['os'],
            'os_version' => $uaParsed['os_version'],
            'screen_width' => $request->input('screen_width'),
            'screen_height' => $request->input('screen_height'),
            'ip_address' => $request->ip(),
            'country_code' => $location['country_code'] ?? null,
            'region' => $location['region'] ?? null,
            'city' => $location['city'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'occurred_at' => now(),
        ]);

        // Update or create session
        $this->updateSession($sessionId, $visitorId, $request, $event);

        // Stamp the order with its channel so the analytics dashboard can scope
        // revenue + tickets per traffic source. Only Purchase events with a
        // resolved order_id propagate — and only when the request explicitly
        // says channel='whitelabel' (or any non-default), so a future
        // marketplace re-tracking won't clobber an existing whitelabel stamp.
        if ($event->event_type === 'purchase'
            && $resolvedOrderId
            && $request->input('channel')
            && $request->input('channel') !== 'marketplace'
        ) {
            try {
                \DB::table('orders')->where('id', $resolvedOrderId)->update([
                    'channel' => $request->input('channel'),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Failed to stamp order.channel from tracking event', [
                    'order_id' => $resolvedOrderId,
                    'channel' => $request->input('channel'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update CoreCustomer metrics (link visitor to customer if email known)
        $this->updateCoreCustomer($visitorId, $request, $event);

        // INSTANT: Write to Redis for real-time analytics (globe, live visitors)
        $eventId = $request->input('marketplace_event_id');
        if ($eventId) {
            $this->redisAnalytics->trackVisitor(
                (int) $eventId,
                $visitorId,
                $location,
                $request->input('event_type')
            );
        }

        // Etapa 4 — Layer B: bridge to Facebook Conversions API server-side.
        // Wrapped to never break the original tracking response.
        try {
            $this->dispatchToFacebookCapi($event, $request);
        } catch (\Throwable $e) {
            \Log::warning('FB CAPI bridge dispatch failed', [
                'core_event_id' => $event->id,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
        }

        // Etapa 5 — server-side dispatch to GA4 Measurement Protocol
        // and TikTok Events API. Both use tracking_integrations rows
        // with encrypted credentials (api_secret / access_token) and
        // apply per-provider organizer→marketplace fallback so a
        // marketplace-level config still fires on organizer pages
        // when the organizer left that provider blank.
        try {
            $this->dispatchToGa4Mp($event, $request);
        } catch (\Throwable $e) {
            \Log::warning('GA4 MP bridge dispatch failed', [
                'core_event_id' => $event->id,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->dispatchToTiktokEventsApi($event, $request);
        } catch (\Throwable $e) {
            \Log::warning('TikTok EAPI bridge dispatch failed', [
                'core_event_id' => $event->id,
                'event_type' => $event->event_type,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Map internal event_type → Meta CAPI event name.
     * Returns null for unsupported types (silently dropped).
     * 'purchase' is handled by Layer C (FacebookCapiOrderObserver) so we
     * don't double-send.
     */
    protected function mapToCapiEventName(string $eventType): ?string
    {
        return match ($eventType) {
            'page_view' => 'PageView',
            'view_item' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'InitiateCheckout',
            'sign_up' => 'CompleteRegistration',
            'lead' => 'Lead',
            default => null,
        };
    }

    /**
     * Dispatch a CAPI event to the organizer's connection if configured.
     *
     * Verbose Log::info temporarily added at every exit branch so the
     * laravel log shows exactly why an event was/wasn't dispatched. Once
     * the funnel is confirmed end-to-end on production we can downgrade
     * these to debug or remove.
     */
    protected function dispatchToFacebookCapi(CoreCustomerEvent $event, Request $request): void
    {
        $marketplaceEventId = $event->marketplace_event_id ?? $request->input('marketplace_event_id');
        if (!$marketplaceEventId) {
            // Expected on global pages (homepage, listings, search, cart,
            // checkout) — they don't belong to a single organizer. Silent
            // by design; would otherwise flood production logs.
            return;
        }

        // Resolve organizer from BOTH possible tables. The Ambilet API
        // returns rows from the `events` table (Event model); some flows
        // populate `marketplace_events` as well. Look in both, in order
        // of likelihood.
        $organizerId = \DB::table('events')
            ->where('id', $marketplaceEventId)
            ->value('marketplace_organizer_id');

        if (!$organizerId) {
            $organizerId = \DB::table('marketplace_events')
                ->where('id', $marketplaceEventId)
                ->value('marketplace_organizer_id');
        }

        if (!$organizerId) {
            \Log::info('FB CAPI bridge: skip — no organizer for event_id', [
                'marketplace_event_id' => $marketplaceEventId,
                'event_type' => $event->event_type,
            ]);
            return;
        }

        // Skip dispatch entirely if this organizer has no active CAPI
        // connection. Without this guard, EVERY tracking call queues a job
        // that immediately exits in handle() — fills the queue table, the
        // log file, and worker cycles for nothing.
        // Cached 60s so high-traffic pages don't hit the DB on every event.
        $hasActiveCapi = \Illuminate\Support\Facades\Cache::remember(
            "fb_capi_active:org:{$organizerId}",
            60,
            fn () => \DB::table('facebook_capi_connections')
                ->where('marketplace_organizer_id', $organizerId)
                ->where('status', 'active')
                ->exists()
        );

        if (!$hasActiveCapi) {
            return; // silent — most organizers don't have CAPI; logging would flood
        }

        $capiEventName = $this->mapToCapiEventName((string) $event->event_type);
        if (!$capiEventName) {
            \Log::info('FB CAPI bridge: skip — unmapped event_type', [
                'event_type' => $event->event_type,
                'organizer_id' => $organizerId,
            ]);
            return;
        }

        $userData = $this->buildCapiUserData($event, $request);
        $customData = $this->buildCapiCustomData($event, $request);

        $clientEventId = $request->input('client_event_id');
        $finalEventId = $clientEventId
            ? (string) $clientEventId
            : 'srv_' . $event->id;

        \Log::info('FB CAPI bridge: dispatching', [
            'organizer_id' => $organizerId,
            'capi_event_name' => $capiEventName,
            'event_id' => $finalEventId,
            'core_event_id' => $event->id,
        ]);

        \App\Jobs\SendFacebookCapiEventJob::dispatch(
            (int) $organizerId,
            $capiEventName,
            $userData,
            $customData,
            $finalEventId,
            $event->page_url ?: null,
            'tracking_event',
            (string) $event->id,
        );
    }

    /**
     * Resolve the tracking_integrations row that governs server-side
     * dispatch for a provider on this event. Delegates to the shared
     * TrackingIntegration::resolveForServerSide() so Layer B (this
     * controller) and Layer C (ServerSidePurchase jobs) agree on which
     * account to hit for a given (organizer, marketplace) tuple.
     *
     * Cached 60s to avoid a double-hit on every event on high-traffic pages.
     */
    protected function resolveTrackingIntegration(
        string $provider,
        ?int $marketplaceOrganizerId,
        ?int $marketplaceClientId
    ): ?\App\Models\TrackingIntegration {
        $cacheKey = "tracking_int_srv:{$provider}:o:" . ((int) $marketplaceOrganizerId)
            . ":c:" . ((int) $marketplaceClientId);

        $resolvedId = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($provider, $marketplaceOrganizerId, $marketplaceClientId) {
            return \App\Models\TrackingIntegration::resolveForServerSide(
                $provider,
                $marketplaceOrganizerId,
                $marketplaceClientId,
            )?->id;
        });

        return $resolvedId ? \App\Models\TrackingIntegration::find($resolvedId) : null;
    }

    /**
     * Look up the marketplace client id for a marketplace_event_id.
     * Cached 5min because it never changes for a given event id.
     * Returns null on global pages that don't carry an event id (home,
     * listing, search).
     */
    protected function resolveMarketplaceClientId(Request $request, ?int $marketplaceEventId): ?int
    {
        $fromRequest = $request->input('marketplace_client_id');
        if (filled($fromRequest)) {
            return (int) $fromRequest;
        }

        if ($client = $request->attributes->get('marketplace_client')) {
            return (int) $client->id;
        }

        if ($marketplaceEventId) {
            return \Illuminate\Support\Facades\Cache::remember(
                "mkt_client_of_event:{$marketplaceEventId}",
                300,
                function () use ($marketplaceEventId) {
                    return \DB::table('events')->where('id', $marketplaceEventId)->value('marketplace_client_id')
                        ?? \DB::table('marketplace_events')->where('id', $marketplaceEventId)->value('marketplace_client_id');
                }
            );
        }

        return null;
    }

    /**
     * Resolve the organizer id for a marketplace_event_id. Same table
     * probe pattern as dispatchToFacebookCapi() — kept as a helper so
     * the two other dispatchers reuse it.
     */
    protected function resolveMarketplaceOrganizerId(?int $marketplaceEventId): ?int
    {
        if (!$marketplaceEventId) {
            return null;
        }
        return \Illuminate\Support\Facades\Cache::remember(
            "mkt_org_of_event:{$marketplaceEventId}",
            300,
            function () use ($marketplaceEventId) {
                return \DB::table('events')->where('id', $marketplaceEventId)->value('marketplace_organizer_id')
                    ?? \DB::table('marketplace_events')->where('id', $marketplaceEventId)->value('marketplace_organizer_id');
            }
        );
    }

    /**
     * Server-side GA4 Measurement Protocol bridge. Unlike Meta CAPI,
     * this ALSO fires on global marketplace pages (home, listing) when
     * the marketplace has a GA4 property + api_secret configured —
     * because those pages are legitimate content for the marketplace's
     * own analytics account.
     */
    protected function dispatchToGa4Mp(CoreCustomerEvent $event, Request $request): void
    {
        $marketplaceEventId = $event->marketplace_event_id ?? $request->input('marketplace_event_id');
        $organizerId = $this->resolveMarketplaceOrganizerId((int) $marketplaceEventId ?: null);
        $clientId = $this->resolveMarketplaceClientId($request, (int) $marketplaceEventId ?: null);

        $integration = $this->resolveTrackingIntegration('ga4', $organizerId, $clientId);
        if (!$integration) {
            return;
        }

        $params = $this->buildGa4Params($event);
        $userProperties = $this->buildGa4UserProperties($event, $request);

        // client_id for GA4 = stable per-user identifier; visitor_id is
        // our internal fingerprint (sha256 of ip+ua+lang). GA4 accepts
        // any string; keeping it aligned with our fingerprint means
        // reports match our internal analytics.
        $ga4ClientId = $event->visitor_id ?: (string) Str::uuid();

        \App\Jobs\SendGa4MpEventJob::dispatch(
            $integration->id,
            $ga4ClientId,
            (string) $event->event_type,
            $params,
            $userProperties,
        );
    }

    /**
     * Server-side TikTok Events API bridge. Deduplicates against the
     * browser ttq.track() call via `client_event_id` when the browser
     * supplied one — otherwise falls back to a stable server-generated
     * id so retries never fire twice.
     */
    protected function dispatchToTiktokEventsApi(CoreCustomerEvent $event, Request $request): void
    {
        $marketplaceEventId = $event->marketplace_event_id ?? $request->input('marketplace_event_id');
        $organizerId = $this->resolveMarketplaceOrganizerId((int) $marketplaceEventId ?: null);
        $clientId = $this->resolveMarketplaceClientId($request, (int) $marketplaceEventId ?: null);

        $integration = $this->resolveTrackingIntegration('tiktok', $organizerId, $clientId);
        if (!$integration) {
            return;
        }

        $clientEventId = $request->input('client_event_id');
        $finalEventId = $clientEventId
            ? (string) $clientEventId
            : 'srv_tt_' . $event->id;

        $userData = $this->buildTiktokUserData($event, $request);
        $properties = $this->buildTiktokProperties($event);
        $page = array_filter([
            'url' => $event->page_url ?: null,
            'referrer' => $event->referrer ?: null,
        ]);

        \App\Jobs\SendTiktokEventsApiJob::dispatch(
            $integration->id,
            (string) $event->event_type,
            $finalEventId,
            $userData,
            $properties,
            $page,
            null,
        );
    }

    /**
     * Build GA4 MP `params` object from our core event fields.
     * Reference: GA4 recommended event schema.
     */
    protected function buildGa4Params(CoreCustomerEvent $event): array
    {
        $params = [];

        if ($event->page_url) {
            $params['page_location'] = $event->page_url;
        }
        if ($event->page_title) {
            $params['page_title'] = $event->page_title;
        }

        if ($event->event_value !== null) {
            $params['value'] = (float) $event->event_value;
        }
        if ($event->currency) {
            $params['currency'] = $event->currency;
        }

        if ($event->content_id) {
            $params['items'] = [array_filter([
                'item_id' => (string) $event->content_id,
                'item_name' => $event->content_name ?: null,
                'item_category' => $event->content_type ?: null,
                'quantity' => $event->quantity ? (int) $event->quantity : 1,
                'price' => $event->event_value !== null ? (float) $event->event_value : null,
            ])];
        }

        // purchase-specific: transaction_id is required by GA4 to dedupe
        // and to power ecommerce reports. Use the order_id when present.
        if ($event->event_type === 'purchase' && $event->order_id) {
            $params['transaction_id'] = (string) $event->order_id;
        }

        return $params;
    }

    /**
     * User properties in GA4 are user-scoped attributes (not event-scoped).
     * Only email-hash goes here — sensitive PII must NOT be sent as-is
     * to MP; GA4 enhanced conversions handle hashing on their side but
     * for the generic MP call we send only the visitor id / language.
     */
    protected function buildGa4UserProperties(CoreCustomerEvent $event, Request $request): array
    {
        return array_filter([
            'visitor_id' => $event->visitor_id ?: null,
            'session_id' => $event->session_id ?: null,
            'locale' => $request->header('Accept-Language') ? substr((string) $request->header('Accept-Language'), 0, 5) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Build TikTok Events API `user` object. Email, phone, external_id
     * will be SHA-256 hashed inside the client before send (TikTok
     * requirement). Non-PII identifiers (ttclid, ttp, ip, user_agent)
     * pass through unhashed.
     */
    protected function buildTiktokUserData(CoreCustomerEvent $event, Request $request): array
    {
        return array_filter([
            'email' => $request->input('email') ?? $request->input('customer_email') ?: null,
            'phone' => $request->input('phone') ?? $request->input('customer_phone') ?: null,
            'external_id' => $event->visitor_id ?: null,
            'ttclid' => $event->ttclid ?? null,
            'ttp' => $request->input('ttp') ?: null,
            'ip' => $event->ip_address ?: $request->ip(),
            'user_agent' => $request->userAgent(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * TikTok event properties: value, currency, contents, content_ids.
     * Purchase adds `content_type: product_group` per TikTok's spec.
     */
    protected function buildTiktokProperties(CoreCustomerEvent $event): array
    {
        $props = [];

        if ($event->event_value !== null) {
            $props['value'] = (float) $event->event_value;
        }
        if ($event->currency) {
            $props['currency'] = $event->currency;
        }
        if ($event->content_id) {
            $props['content_id'] = (string) $event->content_id;
            $props['content_type'] = $event->content_type ?: 'product';
            if ($event->content_name) {
                $props['content_name'] = $event->content_name;
            }
        }
        if ($event->quantity) {
            $props['quantity'] = (int) $event->quantity;
        }

        return $props;
    }

    protected function buildCapiUserData(CoreCustomerEvent $event, Request $request): array
    {
        $email = $request->input('email') ?? $request->input('customer_email');

        $fbc = $request->input('fbc');
        if (!$fbc && $event->fbclid) {
            $fbc = sprintf('fb.1.%d.%s', (int) (microtime(true) * 1000), $event->fbclid);
        }

        return array_filter([
            'em' => $email ?: null,
            'client_ip_address' => $event->ip_address ?: $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbp' => $request->input('fbp') ?: null,
            'fbc' => $fbc ?: null,
            'external_id' => $event->visitor_id ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function buildCapiCustomData(CoreCustomerEvent $event, Request $request): array
    {
        $data = [];

        if ($event->event_value !== null) {
            $data['value'] = (float) $event->event_value;
        }
        if ($event->currency) {
            $data['currency'] = $event->currency;
        }
        if ($event->content_type) {
            $data['content_type'] = $event->content_type;
        } else {
            $data['content_type'] = 'product';
        }
        if ($event->content_id) {
            $data['content_ids'] = [(string) $event->content_id];
        }
        if ($event->content_name) {
            $data['content_name'] = $event->content_name;
        }
        if ($event->quantity) {
            $data['num_items'] = (int) $event->quantity;
        }

        return $data;
    }

    /**
     * Track multiple events in batch
     */
    public function trackBatch(Request $request): JsonResponse
    {
        $request->validate([
            'events' => 'required|array|max:50',
            'events.*.event_type' => 'required|string|max:50',
        ]);

        $results = [];
        foreach ($request->input('events') as $eventData) {
            // Create a sub-request for each event
            $subRequest = new Request($eventData);
            $subRequest->setUserResolver(fn () => $request->user());
            $subRequest->headers->replace($request->headers->all());
            $subRequest->server->set('REMOTE_ADDR', $request->ip());
            // Forward the authenticated marketplace client from middleware
            if ($client = $request->attributes->get('marketplace_client')) {
                $subRequest->attributes->set('marketplace_client', $client);
            }

            try {
                $response = $this->track($subRequest);
                $results[] = json_decode($response->getContent(), true);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Get tracking pixel/beacon (1x1 transparent GIF)
     * Useful for email tracking, etc.
     */
    public function pixel(Request $request): \Illuminate\Http\Response
    {
        // Track the event
        $this->track($request);

        // Return 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Generate a consistent visitor ID from request fingerprint
     */
    protected function generateVisitorId(Request $request): string
    {
        $fingerprint = implode('|', [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language'),
        ]);

        return hash('sha256', $fingerprint);
    }

    /**
     * Parse user agent string into device/browser/OS info
     */
    protected function parseUserAgent(string $ua): array
    {
        // Device type
        $deviceType = 'desktop';
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|Opera Mini|IEMobile/i', $ua)) {
            $deviceType = 'mobile';
        }

        // Device brand
        $deviceBrand = null;
        if (preg_match('/iPhone|iPad|iPod|Macintosh/i', $ua)) $deviceBrand = 'Apple';
        elseif (preg_match('/Samsung/i', $ua)) $deviceBrand = 'Samsung';
        elseif (preg_match('/Huawei/i', $ua)) $deviceBrand = 'Huawei';
        elseif (preg_match('/Xiaomi|Redmi|POCO/i', $ua)) $deviceBrand = 'Xiaomi';

        // Browser
        $browser = null;
        $browserVersion = null;
        if (preg_match('/Edg(?:e|A|iOS)?\/(\S+)/i', $ua, $m)) { $browser = 'Edge'; $browserVersion = $m[1]; }
        elseif (preg_match('/OPR\/(\S+)/i', $ua, $m)) { $browser = 'Opera'; $browserVersion = $m[1]; }
        elseif (preg_match('/Chrome\/(\S+)/i', $ua, $m)) { $browser = 'Chrome'; $browserVersion = $m[1]; }
        elseif (preg_match('/Firefox\/(\S+)/i', $ua, $m)) { $browser = 'Firefox'; $browserVersion = $m[1]; }
        elseif (preg_match('/Safari\/(\S+)/i', $ua, $m) && preg_match('/Version\/(\S+)/i', $ua, $v)) { $browser = 'Safari'; $browserVersion = $v[1]; }

        // OS
        $os = null;
        $osVersion = null;
        if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $m)) { $os = 'Windows'; $osVersion = $m[1]; }
        elseif (preg_match('/Mac OS X (\d+[._]\d+[._]?\d*)/i', $ua, $m)) { $os = 'macOS'; $osVersion = str_replace('_', '.', $m[1]); }
        elseif (preg_match('/Android (\d+[\.\d]*)/i', $ua, $m)) { $os = 'Android'; $osVersion = $m[1]; }
        elseif (preg_match('/iPhone OS (\d+[._]\d+)/i', $ua, $m)) { $os = 'iOS'; $osVersion = str_replace('_', '.', $m[1]); }
        elseif (preg_match('/Linux/i', $ua)) { $os = 'Linux'; }

        return [
            'device_type' => $deviceType,
            'device_brand' => $deviceBrand,
            'browser' => $browser,
            'browser_version' => $browserVersion ? explode('.', $browserVersion)[0] . '.' . (explode('.', $browserVersion)[1] ?? '0') : null,
            'os' => $os,
            'os_version' => $osVersion,
        ];
    }

    /**
     * Determine event category from event type
     */
    protected function determineEventCategory(string $eventType): string
    {
        return match ($eventType) {
            CoreCustomerEvent::TYPE_PAGE_VIEW,
            CoreCustomerEvent::TYPE_SCROLL,
            CoreCustomerEvent::TYPE_CLICK => CoreCustomerEvent::CATEGORY_NAVIGATION,

            CoreCustomerEvent::TYPE_ADD_TO_CART,
            CoreCustomerEvent::TYPE_BEGIN_CHECKOUT,
            CoreCustomerEvent::TYPE_PURCHASE,
            CoreCustomerEvent::TYPE_REFUND,
            CoreCustomerEvent::TYPE_VIEW_ITEM => CoreCustomerEvent::CATEGORY_ECOMMERCE,

            CoreCustomerEvent::TYPE_SIGN_UP,
            CoreCustomerEvent::TYPE_LOGIN => CoreCustomerEvent::CATEGORY_USER,

            CoreCustomerEvent::TYPE_VIDEO_START,
            CoreCustomerEvent::TYPE_VIDEO_PROGRESS,
            CoreCustomerEvent::TYPE_VIDEO_COMPLETE => CoreCustomerEvent::CATEGORY_MEDIA,

            default => CoreCustomerEvent::CATEGORY_ENGAGEMENT,
        };
    }

    /**
     * Update or create session record
     */
    protected function updateSession(string $sessionId, string $visitorId, Request $request, CoreCustomerEvent $event): void
    {
        $t = fn (?string $v, int $max = 255) => $v ? mb_substr($v, 0, $max) : $v;
        $ua = $this->parseUserAgent($request->userAgent() ?? '');

        // The previous SELECT-then-INSERT pattern raced when the same browser
        // fired two events with the same session_id within the same tick (e.g.
        // two open tabs, or a Cloudflare Worker retry). Both passed the check
        // and the second INSERT hit core_sessions_session_id_unique. Use
        // firstOrCreate, then retry the lookup if a parallel request beat us
        // to it — by the time the catch runs, the row is guaranteed to exist.
        try {
            $session = CoreSession::firstOrCreate(
                ['session_id' => $sessionId],
                [
                    'visitor_id' => $visitorId,
                    'marketplace_event_id' => $request->input('marketplace_event_id'),
                    'marketplace_client_id' => $request->input('marketplace_client_id'),
                    'started_at' => now(),
                    'pageviews' => $event->event_type === CoreCustomerEvent::TYPE_PAGE_VIEW ? 1 : 0,
                    'events' => 1,
                    'landing_page' => $t($event->page_url, 2048),
                    'landing_page_type' => $event->page_type,
                    'source' => $t($this->determineSource($request)),
                    'medium' => $t($request->input('utm_medium')),
                    'campaign' => $t($request->input('utm_campaign')),
                    'referrer' => $t($request->input('referrer'), 2048),
                    'utm_source' => $t($request->input('utm_source')),
                    'utm_medium' => $t($request->input('utm_medium')),
                    'utm_campaign' => $t($request->input('utm_campaign')),
                    'gclid' => $t($request->input('gclid')),
                    'fbclid' => $t($request->input('fbclid')),
                    'ttclid' => $t($request->input('ttclid')),
                    'device_type' => $ua['device_type'],
                    'browser' => $ua['browser'],
                    'os' => $ua['os'],
                    'country_code' => $event->country_code,
                    'city' => $event->city,
                ]
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $session = CoreSession::where('session_id', $sessionId)->firstOrFail();
        }

        if ($session->wasRecentlyCreated) {
            return;
        }

        $session->increment('events');
        if ($event->event_type === CoreCustomerEvent::TYPE_PAGE_VIEW) {
            $session->increment('pageviews');
        }
        $session->update([
            'ended_at' => now(),
            'exit_page' => $event->page_url,
            'exit_page_type' => $event->page_type,
            'duration_seconds' => (int) $session->started_at->diffInSeconds(now()),
            'is_bounce' => $session->pageviews <= 1,
        ]);

        if ($event->event_type === CoreCustomerEvent::TYPE_PURCHASE) {
            $session->update([
                'converted' => true,
                'conversion_value' => $event->event_value,
                'conversion_type' => 'purchase',
            ]);
        }
    }

    /**
     * Update CoreCustomer metrics from tracking event
     */
    protected function updateCoreCustomer(string $visitorId, Request $request, CoreCustomerEvent $event): void
    {
        $t = fn (?string $v, int $max = 255) => $v ? mb_substr($v, 0, $max) : $v;
        try {
            $email = $request->input('email') ?? $request->input('customer_email');
            $emailNormalized = $email ? mb_strtolower(trim($email)) : null;

            // Find customer by visitor_id first, fall back to email.
            $customer = CoreCustomer::where('visitor_id', $visitorId)->first();

            if (!$customer && $emailNormalized) {
                $customer = CoreCustomer::findByEmail($emailNormalized);
                if ($customer && !$customer->visitor_id) {
                    $customer->update(['visitor_id' => $visitorId]);
                }
            }

            if (!$customer) {
                // Create minimal customer from visitor data (will be enriched on purchase)
                $customer = CoreCustomer::create([
                    'visitor_id' => $visitorId,
                    'email' => $emailNormalized,
                    'first_name' => $t($request->input('customer_name') ? explode(' ', $request->input('customer_name'), 2)[0] : null),
                    'last_name' => $t($request->input('customer_name') && str_contains($request->input('customer_name'), ' ') ? explode(' ', $request->input('customer_name'), 2)[1] : null),
                    'phone' => $t($request->input('customer_phone')),
                    'ip_address' => $request->ip(),
                    'device_type' => $event->device_type,
                    'browser' => $event->browser,
                    'os' => $event->os,
                    'country_code' => $event->country_code,
                    'city' => $event->city,
                    'region' => $event->region,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'first_source' => $t($this->determineSource($request)),
                    'first_medium' => $t($request->input('utm_medium')),
                    'first_campaign' => $t($request->input('utm_campaign')),
                    'first_referrer' => $t($request->input('referrer'), 2048),
                    'first_landing_page' => $t($request->input('page_url'), 2048),
                    'first_utm_source' => $t($request->input('utm_source')),
                    'first_utm_medium' => $t($request->input('utm_medium')),
                    'first_utm_campaign' => $t($request->input('utm_campaign')),
                    'first_gclid' => $t($request->input('gclid')),
                    'first_fbclid' => $t($request->input('fbclid')),
                    'first_ttclid' => $t($request->input('ttclid')),
                ]);
            } else {
                // Backfill email / name / phone on existing customer when
                // we now have data we didn't have before (typical on
                // purchase events fired from the thank-you page).
                $patch = [];
                if ($emailNormalized && !$customer->email) {
                    $patch['email'] = $emailNormalized;
                }
                if ($request->input('customer_phone') && !$customer->phone) {
                    $patch['phone'] = $t($request->input('customer_phone'));
                }
                $name = trim((string) $request->input('customer_name'));
                if ($name && !$customer->first_name) {
                    $parts = explode(' ', $name, 2);
                    $patch['first_name'] = $t($parts[0] ?? null);
                    if (!empty($parts[1])) {
                        $patch['last_name'] = $t($parts[1]);
                    }
                }
                if (!empty($patch)) {
                    $customer->update($patch);
                }
            }

            // Link event to customer
            $event->update(['customer_id' => $customer->id]);

            // Update visit metrics — also persists last_fbclid / last_gclid /
            // utm_* / referrer on the customer row.
            $customer->recordVisit([
                'referrer' => $request->input('referrer'),
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
                'gclid' => $request->input('gclid'),
                'fbclid' => $request->input('fbclid'),
                'ttclid' => $request->input('ttclid'),
            ]);
        } catch (\Exception $e) {
            // Don't fail the tracking request if customer update fails
            \Log::warning('Failed to update CoreCustomer from tracking: ' . $e->getMessage());
        }
    }

    /**
     * Determine traffic source from request
     */
    protected function determineSource(Request $request): string
    {
        if ($request->input('gclid')) return 'google_ads';
        if ($request->input('fbclid')) return 'facebook_ads';
        if ($request->input('ttclid')) return 'tiktok_ads';
        if ($request->input('utm_source')) return $request->input('utm_source');

        $referrer = $request->input('referrer');
        if (!$referrer) return 'direct';

        $host = parse_url($referrer, PHP_URL_HOST);
        if (!$host) return 'direct';

        if (str_contains($host, 'google')) return 'google';
        if (str_contains($host, 'facebook') || str_contains($host, 'fb.')) return 'facebook';
        if (str_contains($host, 'instagram')) return 'instagram';
        if (str_contains($host, 'tiktok')) return 'tiktok';
        if (str_contains($host, 'twitter') || str_contains($host, 'x.com')) return 'twitter';

        return 'referral';
    }
}
