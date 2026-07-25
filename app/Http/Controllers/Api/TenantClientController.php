<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantArtist;
use App\Models\TenantPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TenantClientController extends Controller
{
    use ResolvesTenant;

    /**
     * Get tenant configuration
     * Supports both domain-based lookup (secure) and ID-based lookup (legacy)
     */
    public function config(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $domainId = $resolved['domain_id'];

        $settings = $tenant->settings ?? [];

        $logo = $settings['branding']['logo'] ?? null;
        $favicon = $settings['branding']['favicon'] ?? null;

        return response()->json([
            'theme' => [
                'primaryColor' => $settings['theme']['primary_color'] ?? '#3B82F6',
                'secondaryColor' => $settings['theme']['secondary_color'] ?? '#1E40AF',
                'logo' => $logo ? Storage::disk('public')->url($logo) : null,
                'favicon' => $favicon ? Storage::disk('public')->url($favicon) : null,
                'fontFamily' => $settings['theme']['font_family'] ?? 'Inter',
            ],
            'site' => [
                'title' => $settings['site_title'] ?? $tenant->public_name ?? $tenant->name,
                'description' => $settings['site_description'] ?? '',
                'tagline' => $settings['site_tagline'] ?? '',
                'language' => $settings['site_language'] ?? 'en',
                'template' => $settings['site_template'] ?? 'default',
            ],
            'social' => [
                'facebook' => $settings['social']['facebook'] ?? null,
                'instagram' => $settings['social']['instagram'] ?? null,
                'twitter' => $settings['social']['twitter'] ?? null,
                'youtube' => $settings['social']['youtube'] ?? null,
                'tiktok' => $settings['social']['tiktok'] ?? null,
                'linkedin' => $settings['social']['linkedin'] ?? null,
            ],
            'pages' => [
                'terms_title' => $settings['legal']['terms_title'] ?? 'Terms & Conditions',
                'privacy_title' => $settings['legal']['privacy_title'] ?? 'Privacy Policy',
            ],
            'menus' => [
                'header' => $this->getMenuPages($tenant, 'header'),
                'footer' => $this->getMenuPages($tenant, 'footer'),
            ],
            'modules' => $this->getEnabledModules($tenant),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->public_name ?? $tenant->name,
                'locale' => $settings['site_language'] ?? 'en',
                'vat_payer' => (bool) $tenant->vat_payer,
            ],
            'platform' => $this->getPlatformBranding(),
        ]);
    }

    /**
     * List events for tenant
     */
    public function events(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;
        $search = $request->query('search');
        $category = $request->query('category');
        $limit = $request->query('limit', 12);
        $offset = $request->query('offset', 0);
        $showAll = $request->query('show_all'); // Debug parameter

        $query = Event::where('tenant_id', $tenantId);

        // Use upcoming scope for filtering (excludes past events and cancelled)
        if (!$showAll) {
            $query->upcoming();
        } else {
            // Even with show_all, exclude cancelled
            $query->where(function ($q) {
                $q->where('is_cancelled', false)
                  ->orWhereNull('is_cancelled');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->where(function ($outer) use ($category) {
                $outer->whereHas('tenantEventCategories', fn ($q) => $q->where('slug', $category))
                    ->orWhereHas('eventTypes', fn ($q) => $q->where('slug', $category));
            });
        }

        $total = $query->count();

        $orderColumn = 'event_date';

        $events = $query->with([
                'venue:id,name,city',
                'eventTypes',
                'tenantEventCategories',
                'artists:id,name,main_image_url',
                'ticketTypes' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderBy($orderColumn, 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $events->map(fn ($event) => $this->formatEvent($event)),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * Get featured events
     */
    public function featuredEvents(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;
        $limit = $request->query('limit', 6);
        $showAll = $request->query('show_all');

        $cacheKey = "featured_events_{$tenantId}_{$limit}" . ($showAll ? '_all' : '');

        $events = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($tenantId, $limit, $showAll) {
            $query = Event::where('tenant_id', $tenantId);

            if (!$showAll) {
                $query->upcoming();
            } else {
                $query->where(function ($q) {
                    $q->where('is_cancelled', false)
                      ->orWhereNull('is_cancelled');
                });
            }

            $query->where('is_featured', true);

            return $query->with([
                    'venue:id,name,city',
                    'eventTypes',
                    'ticketTypes' => fn ($q) => $q->where('status', 'active'),
                ])
                ->orderBy('range_start_date', 'asc')
                ->take($limit)
                ->get();
        });

        return response()->json([
            'data' => $events->map(fn ($event) => $this->formatEvent($event)),
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=120');
    }

    /**
     * Get single event by slug
     */
    public function event(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;

        $event = Event::where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->with([
                'venue',
                'eventTypes',
                'eventGenres',
                'tenantEventCategories',
                'artists:id,name,main_image_url',
                'ticketTypes' => fn ($q) => $q->where('status', 'active'),
            ])
            ->first();

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        return response()->json([
            'data' => $this->formatEventDetail($event),
        ])->header('Cache-Control', 'public, max-age=120, s-maxage=600');
    }

    /**
     * Get event categories (types)
     */
    public function categories(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenantId = $resolved['tenant']->id;
        $locale = app()->getLocale();

        // Prefer tenant's own categories; fall back to global EventTypes used by its events.
        $own = \App\Models\TenantEventCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($own->isNotEmpty()) {
            return response()->json([
                'data' => $own->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->getTranslation('name', $locale) ?: $cat->getTranslation('name', 'ro'),
                    'slug' => $cat->slug,
                    'icon' => $cat->icon ?: 'calendar',
                    'image' => $this->publicUrl($cat->image),
                ]),
            ])->header('Cache-Control', 'public, max-age=300, s-maxage=600');
        }

        $types = Cache::remember("tenant_categories_{$tenantId}", now()->addMinutes(10), function () use ($tenantId) {
            return EventType::whereHas('events', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
                $q->where('status', 'published');
            })->get();
        });

        return response()->json([
            'data' => $types->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->getTranslation('name', $locale),
                'slug' => $type->slug,
                'icon' => $type->icon ?? 'calendar',
            ]),
        ])->header('Cache-Control', 'public, max-age=300, s-maxage=600');
    }

    /**
     * Rezumat comandă pentru pagina de confirmare (thank-you). Scopat pe tenant.
     * Email mascat pentru a limita expunerea PII prin ghicirea id-ului.
     */
    public function orderSummary(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;
        $orderId = (int) $request->query('order');

        $order = \App\Models\Order::where('tenant_id', $tenantId)->find($orderId);
        if (! $order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $locale = app()->getLocale();
        $meta = $order->meta ?? [];

        $event = null;
        if (! empty($meta['event_id'])) {
            $event = Event::with('venue')->find($meta['event_id']);
        }

        $tickets = $order->tickets()->with('ticketType')->get()->map(fn ($t) => [
            'code'       => $t->code,
            'type'       => $t->ticketType?->name,
            'seat_label' => $t->meta['seat_label'] ?? null,
        ])->values();

        // Puncte de fidelitate câștigate (dacă microserviciul e activ)
        $pointsEarned = null;
        $pointsName = null;
        try {
            $gami = app(\App\Services\Gamification\GamificationService::class);
            if ($gami->isEnabled($tenantId)) {
                $config = $gami->getConfig($tenantId);
                if ($config) {
                    $pointsEarned = (int) $config->calculateEarnedPoints((int) ($order->total_cents ?? 0));
                    $pointsName = $config->points_name ?? 'puncte';
                }
            }
        } catch (\Throwable $e) {
            // ignoră
        }

        $email = (string) $order->customer_email;
        $maskedEmail = $email;
        if (str_contains($email, '@')) {
            [$u, $d] = explode('@', $email, 2);
            $maskedEmail = (mb_strlen($u) <= 2 ? mb_substr($u, 0, 1) : mb_substr($u, 0, 1) . str_repeat('*', min(4, mb_strlen($u) - 2)) . mb_substr($u, -1)) . '@' . $d;
        }

        return response()->json([
            'data' => [
                'order_id'   => $order->id,
                'status'     => $order->status,
                'is_paid'    => in_array($order->status, ['paid', 'confirmed', 'completed']),
                'created_at' => $order->created_at?->toIso8601String(),
                'customer_name'  => $meta['customer_name'] ?? null,
                'customer_email' => $maskedEmail,
                'total'      => ($order->total_cents ?? 0) / 100,
                'currency'   => $order->currency ?? 'RON',
                'payment_method' => (($meta['payment_method'] ?? 'card') === 'card_cultural') ? 'Card cultural (demo)' : 'Card (demo)',
                'points_earned'  => $pointsEarned,
                'points_name'    => $pointsName,
                'event'      => $event ? [
                    'title'   => $event->getTranslation('title', $locale),
                    'slug'    => $event->slug,
                    'date'    => $event->event_date?->toIso8601String(),
                    'time'    => $event->start_time,
                    'venue'   => $event->venue?->getTranslation('name', $locale),
                    'city'    => $event->venue?->city,
                    'poster'  => $this->publicUrl($event->poster_url ?: $event->hero_image_url),
                ] : null,
                'tickets'    => $tickets,
            ],
        ]);
    }

    /**
     * Planuri de abonament active ale tenantului (pentru pagina publică).
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;

        $plans = \App\Models\TenantSubscriptionPlan::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $plans->map(fn ($p) => [
                'name'             => $p->name,
                'slug'             => $p->slug,
                'subtitle'         => $p->subtitle,
                'price'            => ($p->price_cents ?? 0) / 100,
                'currency'         => $p->currency ?: 'RON',
                'shows_included'   => $p->shows_included,
                'tickets_included' => $p->tickets_included,
                'seat_mode'        => $p->seat_mode,
                'allowed_sections' => array_values(array_filter($p->allowed_sections ?? [])),
                'validity_mode'    => $p->validity_mode,
                'valid_from'       => $p->valid_from?->toDateString(),
                'valid_until'      => $p->valid_until?->toDateString(),
                'priority_access'  => (bool) $p->priority_access,
                'benefits'         => array_values(array_filter($p->benefits ?? [])),
                'description'      => $p->description,
                'image'            => $this->publicUrl($p->image),
                'is_featured'      => (bool) $p->is_featured,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=300, s-maxage=600');
    }

    /**
     * Metode de plată disponibile pentru tenant. Cardul cultural apare doar
     * dacă microserviciul Netopia e activ + are cultural_card_enabled
     * (oglindește regula din marketplace).
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];

        $methods = [[
            'id'                => 'card',
            'name'              => 'Card bancar',
            'hint'              => 'Visa, Mastercard',
            'surcharge_percent' => 0,
        ]];

        // Card cultural (dacă Netopia activ + activat)
        $pivot = $tenant->microservices()
            ->where('slug', 'payment-netopia')
            ->wherePivot('is_active', true)
            ->first();
        $settings = $pivot?->pivot?->settings ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }
        if (! empty($settings['cultural_card_enabled'])) {
            $methods[] = [
                'id'                => 'card_cultural',
                'name'              => 'Card cultural',
                'hint'              => 'Tichet Cultural, Edenred, Up Cultural',
                'surcharge_percent' => (float) ($settings['cultural_card_surcharge_percent'] ?? 4),
            ];
        }

        return response()->json(['data' => $methods])
            ->header('Cache-Control', 'public, max-age=120');
    }

    /**
     * Public list of tenant artists (troupe/ensemble).
     */
    public function artists(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;

        $artists = TenantArtist::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderByRaw('is_resident DESC')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $artists->map(fn ($a) => $this->formatArtist($a, false))->values(),
        ])->header('Cache-Control', 'public, max-age=300, s-maxage=600');
    }

    /**
     * Public single tenant artist with gallery + bio.
     */
    public function artist(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;

        $artist = TenantArtist::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('slug', $slug)->orWhere('id', $slug))
            ->where('status', 'active')
            ->first();

        if (!$artist) {
            return response()->json(['error' => 'Artist not found'], 404);
        }

        return response()->json([
            'data' => $this->formatArtist($artist, true),
        ]);
    }

    private function formatArtist(TenantArtist $artist, bool $detail = false): array
    {
        $locale = app()->getLocale();
        $photo = $artist->photo_url
            ? (preg_match('#^https?://#', $artist->photo_url) ? $artist->photo_url : Storage::disk('public')->url($artist->photo_url))
            : null;

        $data = [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug ?: (string) $artist->id,
            'role' => $artist->role,
            'is_resident' => (bool) $artist->is_resident,
            'photo_url' => $photo,
        ];

        if ($detail) {
            $data['bio'] = $artist->getTranslation('bio', $locale);
            $data['gallery'] = collect($artist->gallery ?? [])->filter()
                ->map(fn ($p) => preg_match('#^https?://#', $p) ? $p : Storage::disk('public')->url($p))
                ->values()->all();
            $data['email'] = $artist->email;
            $data['phone'] = $artist->phone;
        }

        return $data;
    }

    /**
     * Resolve a stored image path to a public URL. Pass full http(s) URLs
     * through unchanged (e.g. seeded stock images), else build the storage URL.
     */
    private function publicUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        return preg_match('#^https?://#', $path) ? $path : Storage::disk('public')->url($path);
    }

    /**
     * Format event for list view
     */
    private function formatEvent(Event $event): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $event->id,
            'title' => $event->getTranslation('title', $locale),
            'slug' => $event->slug,
            'description' => $event->getTranslation('short_description', $locale) ?? substr(strip_tags($event->getTranslation('description', $locale) ?? ''), 0, 150),

            // Status flags
            'is_sold_out' => $event->is_sold_out ?? false,
            'is_cancelled' => $event->is_cancelled ?? false,

            // Schedule
            'duration_mode' => $event->duration_mode,
            'start_date' => $event->start_date?->toIso8601String(),
            'start_time' => $event->start_time,
            'end_date' => $event->end_date?->toIso8601String(),

            // Location
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->getTranslation('name', $locale),
                'city' => $event->venue->city,
            ] : null,

            // Media
            'poster_url' => $this->publicUrl($event->poster_url),
            'hero_image_url' => $this->publicUrl($event->hero_image_url),

            // Category — prefer tenant's own category, fall back to global EventType
            'category' => (function () use ($event, $locale) {
                $own = $event->relationLoaded('tenantEventCategories') ? $event->tenantEventCategories->first() : null;
                if ($own) {
                    return [
                        'name' => $own->getTranslation('name', $locale) ?: $own->getTranslation('name', 'ro'),
                        'slug' => $own->slug,
                        'icon' => $own->icon,
                    ];
                }
                $type = $event->eventTypes->first();
                return $type ? [
                    'name' => $type->getTranslation('name', $locale),
                    'slug' => $type->slug,
                ] : null;
            })(),

            // Pricing - exclude invitations
            'price_from' => $event->ticketTypes
                ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                ->min('price_max'),
            'currency' => $event->ticketTypes
                ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                ->first()?->currency ?? 'EUR',
        ];
    }

    /**
     * Format event for detail view
     */
    private function formatEventDetail(Event $event): array
    {
        $locale = app()->getLocale();
        $basic = $this->formatEvent($event);

        // Hartă nume -> slug pentru actorii tenantului (link către pagina lor publică)
        $artistSlugs = \App\Models\TenantArtist::where('tenant_id', $event->tenant_id)
            ->pluck('slug', 'name');

        return array_merge($basic, [
            'subtitle' => $event->getTranslation('subtitle', $locale),
            'description' => $event->getTranslation('description', $locale),
            'short_description' => $event->getTranslation('short_description', $locale),
            'gallery' => collect($event->gallery ?? [])->filter()
                ->map(fn ($p) => $this->publicUrl($p))->values()->all(),
            'theater' => [
                'director' => $event->theater_director,
                'lead' => $event->theater_lead,
                'duration' => $event->theater_duration,
                'cast' => collect($event->theater_cast ?? [])
                    ->map(fn ($c) => ['name' => $c['name'] ?? '', 'role' => $c['role'] ?? '', 'slug' => $artistSlugs[$c['name'] ?? ''] ?? null])
                    ->filter(fn ($c) => $c['name'] !== '')->values()->all(),
                'creative' => collect($event->theater_creative ?? [])
                    ->map(fn ($c) => ['role' => $c['role'] ?? '', 'name' => $c['name'] ?? '', 'slug' => $artistSlugs[$c['name'] ?? ''] ?? null])
                    ->filter(fn ($c) => $c['name'] !== '')->values()->all(),
            ],
            'artists' => $event->artists->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'image' => $this->publicUrl($artist->main_image_url),
            ]),
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->getTranslation('name', $locale),
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'latitude' => $event->venue->latitude,
                'longitude' => $event->venue->longitude,
            ] : null,
            // Exclude invitations from ticket types
            'ticket_types' => $event->ticketTypes
                ->filter(fn ($type) => !($type->meta['is_invitation'] ?? false))
                ->map(fn ($type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description ?? '',
                    'sku' => $type->sku,
                    'price' => $type->price_max,
                    'sale_price' => $type->price ?? null,
                    'discount_percent' => $type->price && $type->price_max
                        ? round((1 - ($type->price / $type->price_max)) * 100, 2)
                        : null,
                    'currency' => $type->currency ?? 'EUR',
                    'available' => $type->available_quantity ?? 0,
                    'status' => $type->status,
                ])->values(),
            'genres' => $event->eventGenres->map(fn ($genre) => [
                'name' => $genre->getTranslation('name', $locale),
                'slug' => $genre->slug,
            ]),
        ]);
    }

    /**
     * Get terms page content
     */
    public function terms(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $settings = $tenant->settings ?? [];

        $content = $settings['legal']['terms'] ?? '';

        if (empty($content)) {
            return response()->json(['error' => 'Terms page not found'], 404);
        }

        return response()->json([
            'data' => [
                'title' => $settings['legal']['terms_title'] ?? 'Terms & Conditions',
                'content' => $content,
            ],
        ]);
    }

    /**
     * Get privacy page content
     */
    public function privacy(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $settings = $tenant->settings ?? [];

        $content = $settings['legal']['privacy'] ?? '';

        if (empty($content)) {
            return response()->json(['error' => 'Privacy page not found'], 404);
        }

        return response()->json([
            'data' => [
                'title' => $settings['legal']['privacy_title'] ?? 'Privacy Policy',
                'content' => $content,
            ],
        ]);
    }

    /**
     * Get single page by slug
     */
    public function page(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);

        if (!$resolved) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant = $resolved['tenant'];
        $locale = $request->query('locale', 'en');

        $page = TenantPage::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        return response()->json([
            'data' => [
                'title' => $page->getTranslation('title', $locale) ?? $page->getTranslation('title', 'en'),
                'content' => $page->getTranslation('content', $locale) ?? $page->getTranslation('content', 'en'),
                'slug' => $page->slug,
            ],
        ]);
    }

    /**
     * Get menu pages for a location
     */
    private function getMenuPages(Tenant $tenant, string $location): array
    {
        $locale = app()->getLocale();

        return TenantPage::where('tenant_id', $tenant->id)
            ->where('menu_location', $location)
            ->where('is_published', true)
            ->orderBy('menu_order')
            ->get()
            ->map(fn ($page) => [
                'title' => $page->getTranslation('title', $locale) ?? $page->getTranslation('title', 'en'),
                'slug' => $page->slug,
                'url' => '/page/' . $page->slug,
            ])
            ->toArray();
    }

    /**
     * Get enabled modules for tenant
     */
    private function getEnabledModules(Tenant $tenant): array
    {
        $modules = ['core', 'events', 'auth', 'cart', 'checkout'];

        $microservices = $tenant->microservices()
            ->wherePivot('is_active', true)
            ->get();

        $moduleMap = [
            'seating' => 'seating',
            'affiliates' => 'affiliates',
            'insurance' => 'insurance',
            'whatsapp' => 'whatsapp',
            'promo-codes' => 'promo_codes',
            'shop' => 'shop',
            'gamification' => 'gamification',
        ];

        foreach ($microservices as $ms) {
            if (isset($moduleMap[$ms->slug])) {
                $modules[] = $moduleMap[$ms->slug];
            }
        }

        return array_unique($modules);
    }

    /**
     * Get platform branding (public logos for "Powered by" section)
     */
    private function getPlatformBranding(): array
    {
        $settings = Setting::current();
        $meta = $settings->meta ?? [];

        $getLogoUrl = function ($value) {
            if (empty($value)) {
                return null;
            }
            // Handle array (FileUpload can store as array)
            if (is_array($value)) {
                $value = reset($value);
            }
            if (empty($value)) {
                return null;
            }
            return Storage::disk('public')->url($value);
        };

        return [
            'name' => 'Tixello',
            'url' => 'https://tixello.com',
            'logo_light' => $getLogoUrl($meta['logo_public_light'] ?? null),
            'logo_dark' => $getLogoUrl($meta['logo_public_dark'] ?? null),
        ];
    }
}
