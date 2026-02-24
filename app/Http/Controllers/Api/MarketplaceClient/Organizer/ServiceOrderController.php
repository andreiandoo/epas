<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
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
            ->with(['event:id,name,slug,starts_at,image']);

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
            ->with(['event:id,name,slug,starts_at,image,venue_display_name'])
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
            'event_id' => 'required|exists:events,id',
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
                $recipientCount = $config['recipient_count'] ?? 0;
                $pricePerEmail = $audienceType === 'own'
                    ? ($pricing['own_per_email'] ?? 0.40)
                    : ($pricing['marketplace_per_email'] ?? 0.50);
                return $recipientCount * $pricePerEmail;

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
            return $title['ro'] ?? $title['en'] ?? reset($title) ?? '';
        }

        return (string) ($title ?? '');
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

        return array_merge($base, [
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
                'starts_at' => $order->event->starts_at?->toIso8601String(),
                'venue' => $order->event->venue_display_name,
                'image' => $order->event->image_url ?? null,
            ] : null,
        ]);
    }

    /**
     * Get email audience counts for email marketing campaigns
     */
    public function emailAudiences(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $audienceType = $request->get('audience_type', 'own');
        $eventId = $request->get('event_id');

        // Get filter parameters (support both single values and arrays)
        $ageMin = $request->get('age_min');
        $ageMax = $request->get('age_max');
        $cities = $request->get('cities', []);
        $categories = $request->get('categories', []);
        $genres = $request->get('genres', []);

        // Normalize to arrays
        if (!is_array($cities)) $cities = $cities ? [$cities] : [];
        if (!is_array($categories)) $categories = $categories ? [$categories] : [];
        if (!is_array($genres)) $genres = $genres ? [$genres] : [];

        if ($audienceType === 'own') {
            // Own audience: customers who have ordered from this organizer
            $baseQuery = MarketplaceCustomer::query()
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->whereHas('orders', function ($q) use ($organizer) {
                    $q->where('marketplace_organizer_id', $organizer->id)
                      ->where('status', 'completed');
                });

            $totalCount = (clone $baseQuery)->count();

            // Apply filters
            $filteredQuery = clone $baseQuery;

            if ($ageMin) {
                $minDate = now()->subYears((int) $ageMin);
                $filteredQuery->where('birth_date', '<=', $minDate);
            }

            if ($ageMax) {
                $maxDate = now()->subYears((int) $ageMax + 1)->addDay();
                $filteredQuery->where('birth_date', '>=', $maxDate);
            }

            if (!empty($cities)) {
                $filteredQuery->where(function ($q) use ($cities) {
                    foreach ($cities as $city) {
                        $q->orWhere('city', 'like', "%{$city}%");
                    }
                });
            }

            // Category and genre filtering through orders -> events
            if (!empty($categories) || !empty($genres)) {
                $filteredQuery->whereHas('orders', function ($q) use ($organizer, $categories, $genres) {
                    $q->where('marketplace_organizer_id', $organizer->id)
                      ->where('status', 'completed')
                      ->whereHas('marketplaceEvent', function ($eq) use ($categories, $genres) {
                          if (!empty($categories)) {
                              $eq->whereIn('event_type_id', $categories);
                          }
                          if (!empty($genres)) {
                              $eq->whereIn('music_genre_id', $genres);
                          }
                      });
                });
            }

            $filteredCount = $filteredQuery->count();
        } else {
            // Marketplace audience: all customers who accepted marketing
            $baseQuery = MarketplaceCustomer::query()
                ->where('marketplace_client_id', $organizer->marketplace_client_id)
                ->where('accepts_marketing', true);

            $totalCount = (clone $baseQuery)->count();

            // Apply filters
            $filteredQuery = clone $baseQuery;

            if ($ageMin) {
                $minDate = now()->subYears((int) $ageMin);
                $filteredQuery->where('birth_date', '<=', $minDate);
            }

            if ($ageMax) {
                $maxDate = now()->subYears((int) $ageMax + 1)->addDay();
                $filteredQuery->where('birth_date', '>=', $maxDate);
            }

            if (!empty($cities)) {
                $filteredQuery->where(function ($q) use ($cities) {
                    foreach ($cities as $city) {
                        $q->orWhere('city', 'like', "%{$city}%");
                    }
                });
            }

            // Category and genre filtering through orders -> events
            if (!empty($categories) || !empty($genres)) {
                $filteredQuery->whereHas('orders', function ($q) use ($categories, $genres) {
                    $q->where('status', 'completed')
                      ->whereHas('marketplaceEvent', function ($eq) use ($categories, $genres) {
                          if (!empty($categories)) {
                              $eq->whereIn('event_type_id', $categories);
                          }
                          if (!empty($genres)) {
                              $eq->whereIn('music_genre_id', $genres);
                          }
                      });
                });
            }

            $filteredCount = $filteredQuery->count();
        }

        return $this->success([
            'total_count' => $totalCount,
            'filtered_count' => $filteredCount,
        ]);
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
