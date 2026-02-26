<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceOrderController extends BaseController
{
    /**
     * Get service pricing configuration
     */
    public function pricing(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $types = ServiceType::getOrCreateForMarketplace($organizer->marketplace_client_id);

        $pricing = [];
        foreach ($types as $code => $type) {
            $pricing[$code] = $type->pricing;
        }

        return $this->success(['pricing' => $pricing]);
    }

    /**
     * Get available service types
     */
    public function types(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $types = ServiceType::getOrCreateForMarketplace($organizer->marketplace_client_id);

        $formatted = [];
        foreach ($types as $type) {
            $formatted[] = [
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'pricing' => $type->pricing,
                'is_active' => $type->is_active,
            ];
        }

        return $this->success(['types' => $formatted]);
    }

    /**
     * Get organizer's active services
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = ServiceOrder::forOrganizer($organizer->id)
            ->with('event');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('service_type', $request->type);
        }

        if ($request->has('event_id')) {
            $query->where('marketplace_event_id', $request->event_id);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders, function ($order) {
            return $this->formatOrder($order);
        });
    }

    /**
     * Get service statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $activeCount = ServiceOrder::forOrganizer($organizer->id)
            ->where('status', ServiceOrder::STATUS_ACTIVE)
            ->count();

        $totalSpent = ServiceOrder::forOrganizer($organizer->id)
            ->where('payment_status', ServiceOrder::PAYMENT_PAID)
            ->sum('total');

        $emailsSent = ServiceOrder::forOrganizer($organizer->id)
            ->where('service_type', ServiceOrder::TYPE_EMAIL)
            ->where('status', ServiceOrder::STATUS_COMPLETED)
            ->sum('sent_count');

        return $this->success([
            'active_count' => $activeCount,
            'total_spent' => (float) $totalSpent,
            'emails_sent' => (int) $emailsSent,
        ]);
    }

    /**
     * Get single order details
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $order = ServiceOrder::forOrganizer($organizer->id)
            ->with('event')
            ->where('uuid', $uuid)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        return $this->success([
            'order' => $this->formatOrderDetailed($order),
        ]);
    }

    /**
     * Create a new service order
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validator = Validator::make($request->all(), [
            'service_type' => 'required|in:featuring,email,tracking,campaign',
            'event_id' => 'required|integer',
            'payment_method' => 'nullable|in:card,transfer',
            'config' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors()->toArray());
        }

        // Verify event belongs to organizer
        $event = Event::where('id', $request->event_id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Get pricing
        $types = ServiceType::getOrCreateForMarketplace($organizer->marketplace_client_id);
        $serviceType = $types[$request->service_type] ?? null;

        if (!$serviceType || !$serviceType->is_active) {
            return $this->error('Service type not available', 400);
        }

        // Calculate price (no TVA for extra services)
        $subtotal = $this->calculatePrice($request->service_type, $request->config, $serviceType->pricing);
        $tax = 0;
        $total = $subtotal;

        try {
            $order = ServiceOrder::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'marketplace_event_id' => $event->id,
                'service_type' => $request->service_type,
                'config' => $request->config,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'RON',
                'payment_method' => $request->payment_method,
                'status' => ServiceOrder::STATUS_PENDING_PAYMENT,
                'service_start_date' => $request->config['start_date'] ?? null,
                'service_end_date' => $request->config['end_date'] ?? null,
            ]);

            return $this->success([
                'order' => $this->formatOrderDetailed($order->fresh(['event'])),
            ], 'Order created successfully', 201);

        } catch (\Exception $e) {
            return $this->error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $order = ServiceOrder::forOrganizer($organizer->id)
            ->where('uuid', $uuid)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if (!$order->canBeCancelled()) {
            return $this->error('Order cannot be cancelled', 400);
        }

        $order->cancel();

        return $this->success(null, 'Order cancelled successfully');
    }

    /**
     * Initiate Netopia payment for a pending service order
     */
    public function pay(Request $request, string $uuid): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $client = \App\Models\MarketplaceClient::find($organizer->marketplace_client_id);

        $order = ServiceOrder::where('uuid', $uuid)
            ->where('marketplace_client_id', $client->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('status', ServiceOrder::STATUS_PENDING_PAYMENT)
            ->first();

        if (! $order) {
            return $this->error('Order not found or not payable', 404);
        }

        $defaultPaymentMethod = $client->getDefaultPaymentMethod();
        if (! $defaultPaymentMethod) {
            return $this->error('No payment method configured for this marketplace', 400);
        }

        $processorType = match ($defaultPaymentMethod->slug) {
            'netopia', 'netopia-payments', 'payment-netopia' => 'netopia',
            'stripe', 'stripe-payments', 'payment-stripe'    => 'stripe',
            'euplatesc', 'payment-euplatesc'                 => 'euplatesc',
            'payu', 'payment-payu'                           => 'payu',
            default => $defaultPaymentMethod->slug,
        };

        $paymentConfig = $client->getPaymentMethodSettings($defaultPaymentMethod->slug);
        if (! $paymentConfig) {
            return $this->error('Payment configuration not found', 400);
        }

        // order_number already starts with SVC- prefix (from generateOrderNumber)
        $reference = $order->order_number;

        try {
            $processor = \App\Services\PaymentProcessors\PaymentProcessorFactory::makeFromArray($processorType, $paymentConfig);

            $baseUrl = rtrim($client->domain ?? 'https://bilete.online', '/');
            $paymentData = $processor->createPayment([
                'order_id'       => $reference,
                'order_number'   => $reference,
                'amount'         => $order->total,
                'currency'       => $order->currency ?? 'RON',
                'customer_email' => $organizer->email ?? '',
                'customer_name'  => $organizer->name ?? '',
                'description'    => 'Promovare eveniment - ' . $reference,
                'success_url'    => $baseUrl . '/organizator/servicii?payment=success',
                'return_url'     => $baseUrl . '/organizator/servicii?payment=success',
                'cancel_url'     => $baseUrl . '/organizator/servicii?payment=cancel',
                'callback_url'   => route('api.marketplace-client.payment.callback', ['client' => $client->slug]),
                'metadata'       => ['source' => 'service_order'],
            ]);

            $order->update(['payment_reference' => $reference]);

            $response = [
                'payment_url'       => $paymentData['redirect_url'] ?? $paymentData['payment_url'] ?? null,
                'payment_reference' => $reference,
                'processor'         => $processorType,
            ];

            if (($paymentData['method'] ?? 'GET') === 'POST' && ! empty($paymentData['form_data'])) {
                $response['method']    = 'POST';
                $response['form_data'] = $paymentData['form_data'];
            }

            return $this->success($response, 'Payment initiated');

        } catch (\Exception $e) {
            return $this->error('Failed to initiate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calculate price based on service type and config
     */
    protected function calculatePrice(string $type, array $config, array $pricing): float
    {
        switch ($type) {
            case ServiceOrder::TYPE_FEATURING:
                $total = 0;
                $locations = $config['locations'] ?? [];
                $startDate = isset($config['start_date']) ? new \DateTime($config['start_date']) : now();
                $endDate = isset($config['end_date']) ? new \DateTime($config['end_date']) : now()->addDays(7);
                $days = max(1, $startDate->diff($endDate)->days + 1);

                foreach ($locations as $location) {
                    // Map old keys (home/genre) to new keys for backward compatibility
                    $key = match ($location) {
                        'home'  => 'home_hero',
                        'genre' => 'home_recommendations',
                        default => $location,
                    };
                    $dailyRate = $pricing[$key] ?? $pricing[$location] ?? 0;
                    $total += $dailyRate * $days;
                }
                return $total;

            case ServiceOrder::TYPE_EMAIL:
                $audienceType = $config['audience_type'] ?? 'own';
                $pricePerEmail = $audienceType === 'own'
                    ? ($pricing['own_per_email'] ?? 0.40)
                    : ($pricing['marketplace_per_email'] ?? 0.50);
                $perfectCount = $config['perfect_count'] ?? ($config['recipient_count'] ?? 0);
                $partialCount = $config['partial_count'] ?? 0;
                // Partial matches at half price (rounded to 2 decimals for consistency)
                return round(($perfectCount * $pricePerEmail) + ($partialCount * round($pricePerEmail / 2, 2)), 2);

            case ServiceOrder::TYPE_TRACKING:
                $platforms = $config['platforms'] ?? [];
                $months = $config['duration_months'] ?? 1;
                $monthlyRate = $pricing['per_platform_monthly'] ?? 49;
                $discount = $pricing['discounts'][(string) $months] ?? 0;
                $subtotal = count($platforms) * $monthlyRate * $months;
                return $subtotal * (1 - $discount);

            case ServiceOrder::TYPE_CAMPAIGN:
                $campaignType = $config['campaign_type'] ?? 'standard';
                return $pricing[$campaignType] ?? 899;

            default:
                return 0;
        }
    }

    /**
     * Format order for list
     */
    protected function formatOrder(ServiceOrder $order): array
    {
        return [
            'id' => $order->uuid,
            'order_number' => $order->order_number,
            'type' => $order->service_type,
            'type_label' => $order->service_type_label,
            'event_id' => $order->marketplace_event_id,
            'event_name' => $this->getEventTitle($order->event),
            'details' => $this->getOrderDetails($order),
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'status' => $order->status,
            'status_label' => $order->status_label,
            'payment_status' => $order->payment_status,
            'service_start_date' => $order->service_start_date?->format('Y-m-d'),
            'service_end_date' => $order->service_end_date?->format('Y-m-d'),
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }

    protected function getEventTitle(?Event $event): string
    {
        if (! $event) {
            return '';
        }

        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? array_values($title)[0] ?? '';
        }
        return $title ?? '';
    }

    protected function getOrderDetails(ServiceOrder $order): string
    {
        $config = $order->config ?? [];

        $locationLabels = [
            'home_hero'           => 'Prima pagina - Hero',
            'home_recommendations'=> 'Prima pagina - Recomandari',
            'category'            => 'Pagina categorie',
            'city'                => 'Pagina oras',
        ];

        return match ($order->service_type) {
            'featuring' => implode(', ', array_map(
                fn ($loc) => $locationLabels[$loc] ?? $loc,
                $config['locations'] ?? []
            )) ?: '-',
            'email' => (($config['audience_type'] ?? '') === 'own' ? 'Clientii tai' : 'Baza marketplace')
                       . ' - ' . number_format((int) ($config['recipient_count'] ?? 0)) . ' destinatari',
            'tracking' => implode(', ', $config['platforms'] ?? [])
                          . ' (' . ($config['duration_months'] ?? 1) . ' luni)',
            'campaign' => ($config['campaign_type'] ?? 'custom') . ' - ' . number_format((float) ($config['budget'] ?? 0)) . ' RON',
            default => '-',
        };
    }

    /**
     * Format order with full details
     */
    protected function formatOrderDetailed(ServiceOrder $order): array
    {
        $base = $this->formatOrder($order);

        $detailed = array_merge($base, [
            'config' => $order->config,
            'subtotal' => (float) $order->subtotal,
            'tax' => (float) $order->tax,
            'payment_method' => $order->payment_method,
            'payment_reference' => $order->payment_reference,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'scheduled_at' => $order->scheduled_at?->toIso8601String(),
            'executed_at' => $order->executed_at?->toIso8601String(),
            'sent_count' => $order->sent_count,
            'event' => $order->event ? [
                'id' => $order->event->id,
                'name' => $this->getEventTitle($order->event),
                'slug' => $order->event->slug,
                'starts_at' => $order->event->event_date
                    ? \Carbon\Carbon::parse($order->event->event_date->format('Y-m-d') . ' ' . ($order->event->start_time ?? '00:00'))->toIso8601String()
                    : null,
                'venue' => $order->event->venue?->getTranslation('name') ?? null,
                'image' => $order->event->poster_url
                    ? (str_starts_with($order->event->poster_url, 'http') ? $order->event->poster_url : rtrim(config('app.url'), '/') . '/storage/' . ltrim($order->event->poster_url, '/'))
                    : null,
            ] : null,
        ]);

        // Add newsletter stats for email orders
        if ($order->service_type === 'email') {
            $newsletter = $order->getLinkedNewsletter();
            if ($newsletter) {
                $detailed['newsletter'] = [
                    'status' => $newsletter->status,
                    'total_recipients' => $newsletter->total_recipients,
                    'sent_count' => $newsletter->sent_count,
                    'failed_count' => $newsletter->failed_count,
                    'opened_count' => $newsletter->opened_count,
                    'clicked_count' => $newsletter->clicked_count,
                    'unsubscribed_count' => $newsletter->unsubscribed_count ?? 0,
                    'open_rate' => $newsletter->sent_count > 0
                        ? round(($newsletter->opened_count / $newsletter->sent_count) * 100, 1) : 0,
                    'click_rate' => $newsletter->opened_count > 0
                        ? round(($newsletter->clicked_count / $newsletter->opened_count) * 100, 1) : 0,
                    'scheduled_at' => $newsletter->scheduled_at?->toIso8601String(),
                    'completed_at' => $newsletter->completed_at?->toIso8601String(),
                ];
            }
        }

        return $detailed;
    }

    /**
     * Get email audience counts for email marketing campaigns
     */
    public function emailAudiences(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $audienceType = $request->get('audience_type', 'own');

        // Get filter parameters (support both single values and arrays)
        $ageMin = $request->get('age_min');
        $ageMax = $request->get('age_max');
        $gender = $request->get('gender');
        $cities = $request->get('cities', []);
        $categories = $request->get('categories', []);
        $genres = $request->get('genres', []);

        // Normalize to arrays
        if (!is_array($cities)) $cities = $cities ? [$cities] : [];
        if (!is_array($categories)) $categories = $categories ? [$categories] : [];
        if (!is_array($genres)) $genres = $genres ? [$genres] : [];

        $filters = compact('ageMin', 'ageMax', 'gender', 'cities', 'categories', 'genres');

        // Build base query for audience type
        $baseQuery = $this->buildAudienceBaseQuery($organizer, $audienceType);
        $totalCount = (clone $baseQuery)->count();

        // Apply ALL filters → filtered_count
        $filteredQuery = $this->applyAudienceFilters(clone $baseQuery, $filters, $organizer, $audienceType);
        $filteredCount = $filteredQuery->count();

        // Build per-filter breakdowns (only when filters are active)
        $filterCounts = $this->buildFilterBreakdowns($baseQuery, $filters, $organizer, $audienceType);

        return $this->success([
            'total_count' => $totalCount,
            'filtered_count' => $filteredCount,
            'filter_counts' => $filterCounts,
        ]);
    }

    /**
     * Build the base audience query (no filters applied)
     */
    public function buildAudienceBaseQuery(MarketplaceOrganizer $organizer, string $audienceType)
    {
        if ($audienceType === 'own') {
            return MarketplaceCustomer::query()
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->whereHas('orders', function ($q) use ($organizer) {
                    $q->where('marketplace_organizer_id', $organizer->id)
                      ->where('status', 'completed');
                });
        }

        // Marketplace audience: ALL active customers in the marketplace
        return MarketplaceCustomer::query()
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('status', 'active');
    }

    /**
     * Apply audience filters to a query.
     * Filters use multiple data sources:
     *   - City: customer.city OR favorite venues' city
     *   - Category: orders -> marketplace events OR watchlist marketplace events
     *   - Genre: orders -> marketplace events OR watchlist marketplace events
     *   - Gender: customer.gender
     *   - Age: customer.birth_date
     */
    public function applyAudienceFilters($query, array $filters, MarketplaceOrganizer $organizer, string $audienceType)
    {
        $ageMin = $filters['ageMin'] ?? null;
        $ageMax = $filters['ageMax'] ?? null;
        $gender = $filters['gender'] ?? null;
        $cities = $filters['cities'] ?? [];
        $categories = $filters['categories'] ?? [];
        $genres = $filters['genres'] ?? [];

        // Age filter (birth_date based)
        if ($ageMin) {
            $minDate = now()->subYears((int) $ageMin);
            $query->where('birth_date', '<=', $minDate);
        }

        if ($ageMax) {
            $maxDate = now()->subYears((int) $ageMax + 1)->addDay();
            $query->where('birth_date', '>=', $maxDate);
        }

        // Gender filter
        if ($gender) {
            $query->where('gender', $gender);
        }

        // City filter: customer.city OR favorite venue city
        if (!empty($cities)) {
            $query->where(function ($q) use ($cities) {
                $q->where(function ($cq) use ($cities) {
                    foreach ($cities as $city) {
                        $cq->orWhere('city', 'like', "%{$city}%");
                    }
                });
                $q->orWhereHas('favoriteVenues', function ($vq) use ($cities) {
                    $vq->where(function ($vcq) use ($cities) {
                        foreach ($cities as $city) {
                            $vcq->orWhere('city', 'like', "%{$city}%");
                        }
                    });
                });
            });
        }

        // Category filter: via orders OR watchlist marketplace events
        if (!empty($categories)) {
            $query->where(function ($q) use ($organizer, $audienceType, $categories) {
                $q->whereHas('orders', function ($oq) use ($organizer, $audienceType, $categories) {
                    if ($audienceType === 'own') {
                        $oq->where('marketplace_organizer_id', $organizer->id);
                    }
                    $oq->where('status', 'completed')
                       ->whereHas('marketplaceEvent', function ($eq) use ($categories) {
                           $eq->whereIn('marketplace_event_category_id', $categories);
                       });
                });
                $q->orWhereHas('watchlistMarketplaceEvents', function ($wq) use ($categories) {
                    $wq->whereIn('marketplace_event_category_id', $categories);
                });
            });
        }

        // Genre filter: via orders OR watchlist marketplace events
        if (!empty($genres)) {
            $query->where(function ($q) use ($organizer, $audienceType, $genres) {
                $q->whereHas('orders', function ($oq) use ($organizer, $audienceType, $genres) {
                    if ($audienceType === 'own') {
                        $oq->where('marketplace_organizer_id', $organizer->id);
                    }
                    $oq->where('status', 'completed')
                       ->whereHas('marketplaceEvent', function ($eq) use ($genres) {
                           $eq->where(function ($gq) use ($genres) {
                               foreach ($genres as $genre) {
                                   $gq->orWhereJsonContains('genre_ids', (int) $genre);
                               }
                           });
                       });
                });
                $q->orWhereHas('watchlistMarketplaceEvents', function ($wq) use ($genres) {
                    $wq->where(function ($gq) use ($genres) {
                        foreach ($genres as $genre) {
                            $gq->orWhereJsonContains('genre_ids', (int) $genre);
                        }
                    });
                });
            });
        }

        return $query;
    }

    /**
     * Build per-filter count breakdowns
     */
    protected function buildFilterBreakdowns($baseQuery, array $filters, MarketplaceOrganizer $organizer, string $audienceType): array
    {
        $cities = $filters['cities'] ?? [];
        $categories = $filters['categories'] ?? [];
        $genres = $filters['genres'] ?? [];
        $gender = $filters['gender'] ?? null;

        $hasAnyFilter = !empty($filters['ageMin']) || !empty($filters['ageMax'])
            || !empty($cities) || !empty($categories) || !empty($genres) || !empty($gender);

        if (!$hasAnyFilter) {
            return [];
        }

        $result = [];

        // Count: match all filters EXCEPT cities (partial match without city filter)
        if (!empty($cities)) {
            $withoutCity = $this->applyAudienceFilters(
                clone $baseQuery,
                array_merge($filters, ['cities' => []]),
                $organizer, $audienceType
            );
            $result['without_city'] = $withoutCity->count();

            // Per-city counts (with all other filters applied)
            $byCityFilters = array_merge($filters, ['cities' => []]);
            $byCityBase = $this->applyAudienceFilters(clone $baseQuery, $byCityFilters, $organizer, $audienceType);
            $byCities = [];
            foreach ($cities as $city) {
                $cityQuery = (clone $byCityBase)->where(function ($q) use ($city) {
                    $q->where('city', 'like', "%{$city}%")
                      ->orWhereHas('favoriteVenues', function ($vq) use ($city) {
                          $vq->where('city', 'like', "%{$city}%");
                      });
                });
                $byCities[$city] = $cityQuery->count();
            }
            $result['by_city'] = $byCities;
        }

        // Count: match all filters EXCEPT categories
        if (!empty($categories)) {
            $withoutCat = $this->applyAudienceFilters(
                clone $baseQuery,
                array_merge($filters, ['categories' => []]),
                $organizer, $audienceType
            );
            $result['without_category'] = $withoutCat->count();
        }

        // Count: match all filters EXCEPT genres
        if (!empty($genres)) {
            $withoutGenre = $this->applyAudienceFilters(
                clone $baseQuery,
                array_merge($filters, ['genres' => []]),
                $organizer, $audienceType
            );
            $result['without_genre'] = $withoutGenre->count();
        }

        // Count: have birth_date set (for age filter relevance)
        $withBirthDate = (clone $baseQuery)->whereNotNull('birth_date')->count();
        $result['with_birth_date'] = $withBirthDate;

        return $result;
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
}
