<?php

namespace App\Services\Tracking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AlertTriggerService
{
    protected int $tenantId;

    // Alert types with configurations
    protected const ALERT_TYPES = [
        // High-value customer alerts
        'high_value_cart_abandon' => [
            'category' => 'revenue',
            'priority' => 'critical',
            'threshold' => 200, // Cart value threshold
            'cooldown_hours' => 24,
        ],
        'vip_churn_risk' => [
            'category' => 'retention',
            'priority' => 'critical',
            'threshold' => 500, // LTV threshold
            'cooldown_hours' => 168, // 1 week
        ],
        'high_value_purchase' => [
            'category' => 'revenue',
            'priority' => 'info',
            'threshold' => 500, // Purchase value
            'cooldown_hours' => 0, // No cooldown
        ],

        // Engagement alerts
        'repeat_visitor_no_purchase' => [
            'category' => 'conversion',
            'priority' => 'medium',
            'threshold' => 5, // Number of visits
            'cooldown_hours' => 72,
        ],
        'high_intent_signal' => [
            'category' => 'conversion',
            'priority' => 'high',
            'threshold' => 3, // Multiple event views in session
            'cooldown_hours' => 24,
        ],
        'price_drop_interest' => [
            'category' => 'conversion',
            'priority' => 'medium',
            'threshold' => 2, // Views of same event
            'cooldown_hours' => 48,
        ],

        // Anomaly alerts
        'unusual_activity_spike' => [
            'category' => 'security',
            'priority' => 'high',
            'threshold' => 10, // 10x normal activity
            'cooldown_hours' => 1,
        ],
        'bulk_purchase_pattern' => [
            'category' => 'fraud',
            'priority' => 'critical',
            'threshold' => 10, // Tickets in single order
            'cooldown_hours' => 0,
        ],

        // Milestone alerts
        'first_purchase' => [
            'category' => 'milestone',
            'priority' => 'info',
            'threshold' => 1,
            'cooldown_hours' => 0,
        ],
        'loyalty_milestone' => [
            'category' => 'milestone',
            'priority' => 'info',
            'threshold' => 5, // 5th purchase
            'cooldown_hours' => 0,
        ],

        // Event-specific alerts
        'event_selling_fast' => [
            'category' => 'inventory',
            'priority' => 'high',
            'threshold' => 80, // 80% sold
            'cooldown_hours' => 24,
        ],
        'event_almost_soldout' => [
            'category' => 'inventory',
            'priority' => 'critical',
            'threshold' => 95, // 95% sold
            'cooldown_hours' => 12,
        ],
    ];

    // Webhook endpoints by category
    protected array $webhookEndpoints = [];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->loadWebhookEndpoints();
    }

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    /**
     * Check and trigger alerts for a tracking event
     */
    public function processEvent(array $event): array
    {
        $triggeredAlerts = [];

        $eventType = $event['type'] ?? null;
        $personId = $event['person_id'] ?? null;
        $eventData = $event['data'] ?? [];

        if (!$eventType) {
            return $triggeredAlerts;
        }

        // Check each relevant alert type
        switch ($eventType) {
            case 'add_to_cart':
                $triggeredAlerts = array_merge(
                    $triggeredAlerts,
                    $this->checkHighValueCart($personId, $eventData)
                );
                break;

            case 'cart_abandon':
                $triggeredAlerts = array_merge(
                    $triggeredAlerts,
                    $this->checkCartAbandon($personId, $eventData)
                );
                break;

            case 'purchase':
                $triggeredAlerts = array_merge(
                    $triggeredAlerts,
                    $this->checkPurchaseAlerts($personId, $eventData)
                );
                break;

            case 'view_item':
                $triggeredAlerts = array_merge(
                    $triggeredAlerts,
                    $this->checkViewAlerts($personId, $eventData)
                );
                break;

            case 'page_view':
                $triggeredAlerts = array_merge(
                    $triggeredAlerts,
                    $this->checkVisitAlerts($personId, $eventData)
                );
                break;
        }

        // Check person-level alerts
        if ($personId) {
            $triggeredAlerts = array_merge(
                $triggeredAlerts,
                $this->checkPersonAlerts($personId)
            );
        }

        // Fire webhooks for triggered alerts
        foreach ($triggeredAlerts as $alert) {
            $this->fireWebhook($alert);
        }

        return $triggeredAlerts;
    }

    /**
     * Check for high-value cart alerts
     */
    protected function checkHighValueCart(int $personId, array $data): array
    {
        $alerts = [];
        $cartValue = $data['value'] ?? 0;
        $threshold = self::ALERT_TYPES['high_value_cart_abandon']['threshold'];

        if ($cartValue >= $threshold && !$this->isOnCooldown('high_value_cart_abandon', $personId)) {
            $alerts[] = $this->createAlert('high_value_cart_abandon', $personId, [
                'cart_value' => $cartValue,
                'event_id' => $data['event_id'] ?? null,
                'items' => $data['items'] ?? [],
            ]);
        }

        return $alerts;
    }

    /**
     * Check cart abandon alerts
     */
    protected function checkCartAbandon(int $personId, array $data): array
    {
        $alerts = [];
        $cartValue = $data['value'] ?? 0;
        $threshold = self::ALERT_TYPES['high_value_cart_abandon']['threshold'];

        if ($cartValue >= $threshold && !$this->isOnCooldown('high_value_cart_abandon', $personId)) {
            // Check if high-value customer
            $customer = DB::table('core_customers')
                ->where('tenant_id', $this->tenantId)
                ->where('id', $personId)
                ->first();

            $priority = ($customer && $customer->ltv >= 500) ? 'critical' : 'high';

            $alerts[] = $this->createAlert('high_value_cart_abandon', $personId, [
                'cart_value' => $cartValue,
                'customer_ltv' => $customer->ltv ?? 0,
                'priority_override' => $priority,
            ]);
        }

        return $alerts;
    }

    /**
     * Check purchase-related alerts
     */
    protected function checkPurchaseAlerts(int $personId, array $data): array
    {
        $alerts = [];
        $orderValue = $data['value'] ?? 0;
        $quantity = $data['quantity'] ?? 1;

        // High-value purchase
        if ($orderValue >= self::ALERT_TYPES['high_value_purchase']['threshold']) {
            $alerts[] = $this->createAlert('high_value_purchase', $personId, [
                'order_value' => $orderValue,
                'event_id' => $data['event_id'] ?? null,
            ]);
        }

        // Bulk purchase (potential fraud or reseller)
        if ($quantity >= self::ALERT_TYPES['bulk_purchase_pattern']['threshold']) {
            $alerts[] = $this->createAlert('bulk_purchase_pattern', $personId, [
                'quantity' => $quantity,
                'order_value' => $orderValue,
                'event_id' => $data['event_id'] ?? null,
            ]);
        }

        // Check for milestones
        $customer = DB::table('core_customers')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $personId)
            ->first();

        if ($customer) {
            $totalPurchases = ($customer->total_purchases ?? 0) + 1;

            // First purchase
            if ($totalPurchases == 1) {
                $alerts[] = $this->createAlert('first_purchase', $personId, [
                    'order_value' => $orderValue,
                ]);
            }

            // Loyalty milestones (5th, 10th, 25th, etc.)
            if (in_array($totalPurchases, [5, 10, 25, 50, 100])) {
                $alerts[] = $this->createAlert('loyalty_milestone', $personId, [
                    'purchase_count' => $totalPurchases,
                    'total_spent' => ($customer->total_spent ?? 0) + $orderValue,
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Check view-related alerts
     */
    protected function checkViewAlerts(int $personId, array $data): array
    {
        $alerts = [];
        $eventId = $data['event_id'] ?? null;

        if (!$eventId) {
            return $alerts;
        }

        // Check for high-intent signals (multiple views of same event)
        $recentViews = $this->getRecentEventViews($personId, $eventId, 24);

        if ($recentViews >= self::ALERT_TYPES['high_intent_signal']['threshold']) {
            if (!$this->isOnCooldown('high_intent_signal', $personId)) {
                $alerts[] = $this->createAlert('high_intent_signal', $personId, [
                    'event_id' => $eventId,
                    'view_count' => $recentViews,
                    'suggested_action' => 'send_reminder_or_offer',
                ]);
            }
        }

        // Check for price drop interest (views without purchase)
        if ($recentViews >= self::ALERT_TYPES['price_drop_interest']['threshold']) {
            if (!$this->hasPurchasedEvent($personId, $eventId) &&
                !$this->isOnCooldown('price_drop_interest', $personId)) {
                $alerts[] = $this->createAlert('price_drop_interest', $personId, [
                    'event_id' => $eventId,
                    'view_count' => $recentViews,
                    'suggested_action' => 'notify_on_price_drop',
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Check visit-related alerts
     */
    protected function checkVisitAlerts(int $personId, array $data): array
    {
        $alerts = [];

        // Check for repeat visitor without purchase
        $customer = DB::table('core_customers')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $personId)
            ->first();

        if ($customer) {
            $visits = $customer->total_visits ?? 0;
            $purchases = $customer->total_purchases ?? 0;

            if ($visits >= self::ALERT_TYPES['repeat_visitor_no_purchase']['threshold'] &&
                $purchases == 0 &&
                !$this->isOnCooldown('repeat_visitor_no_purchase', $personId)) {
                $alerts[] = $this->createAlert('repeat_visitor_no_purchase', $personId, [
                    'visit_count' => $visits,
                    'suggested_action' => 'send_first_purchase_incentive',
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Check person-level alerts (churn risk, etc.)
     */
    protected function checkPersonAlerts(int $personId): array
    {
        $alerts = [];

        $customer = DB::table('core_customers')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $personId)
            ->first();

        if (!$customer) {
            return $alerts;
        }

        // VIP churn risk
        $ltvThreshold = self::ALERT_TYPES['vip_churn_risk']['threshold'];
        if (($customer->ltv ?? 0) >= $ltvThreshold &&
            in_array($customer->churn_risk, ['high', 'critical']) &&
            !$this->isOnCooldown('vip_churn_risk', $personId)) {
            $alerts[] = $this->createAlert('vip_churn_risk', $personId, [
                'ltv' => $customer->ltv,
                'churn_risk' => $customer->churn_risk,
                'days_since_last_visit' => $customer->last_seen_at
                    ? now()->diffInDays($customer->last_seen_at)
                    : null,
                'suggested_action' => 'immediate_winback_outreach',
            ]);
        }

        return $alerts;
    }

    /**
     * Check event inventory alerts
     */
    public function checkEventInventory(int $eventId): array
    {
        $alerts = [];

        $event = DB::table('events')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $eventId)
            ->first();

        if (!$event || !$event->capacity) {
            return $alerts;
        }

        $soldCount = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->sum('quantity');

        $soldPercentage = ($soldCount / $event->capacity) * 100;

        // Almost sold out
        if ($soldPercentage >= self::ALERT_TYPES['event_almost_soldout']['threshold']) {
            if (!$this->isOnCooldown('event_almost_soldout', $eventId, 'event')) {
                $alerts[] = $this->createAlert('event_almost_soldout', null, [
                    'event_id' => $eventId,
                    'event_name' => $event->name ?? 'Unknown',
                    'sold_percentage' => round($soldPercentage, 1),
                    'remaining_tickets' => $event->capacity - $soldCount,
                ], 'event', $eventId);
            }
        }
        // Selling fast
        elseif ($soldPercentage >= self::ALERT_TYPES['event_selling_fast']['threshold']) {
            if (!$this->isOnCooldown('event_selling_fast', $eventId, 'event')) {
                $alerts[] = $this->createAlert('event_selling_fast', null, [
                    'event_id' => $eventId,
                    'event_name' => $event->name ?? 'Unknown',
                    'sold_percentage' => round($soldPercentage, 1),
                    'remaining_tickets' => $event->capacity - $soldCount,
                ], 'event', $eventId);
            }
        }

        return $alerts;
    }

    /**
     * Create alert record
     */
    protected function createAlert(
        string $type,
        ?int $personId,
        array $data,
        string $entityType = 'person',
        ?int $entityId = null
    ): array {
        $config = self::ALERT_TYPES[$type];

        $alert = [
            'id' => uniqid('alert_'),
            'tenant_id' => $this->tenantId,
            'type' => $type,
            'category' => $config['category'],
            'priority' => $data['priority_override'] ?? $config['priority'],
            'person_id' => $personId,
            'entity_type' => $entityType,
            'entity_id' => $entityId ?? $personId,
            'data' => $data,
            'created_at' => now()->toIso8601String(),
        ];

        // Store alert
        $this->storeAlert($alert);

        // Set cooldown
        $cooldownKey = $this->getCooldownKey($type, $entityId ?? $personId, $entityType);
        if ($config['cooldown_hours'] > 0) {
            Cache::put($cooldownKey, true, now()->addHours($config['cooldown_hours']));
        }

        return $alert;
    }

    /**
     * Store alert in database
     */
    protected function storeAlert(array $alert): void
    {
        try {
            DB::table('tracking_alerts')->insert([
                'id' => $alert['id'],
                'tenant_id' => $alert['tenant_id'],
                'type' => $alert['type'],
                'category' => $alert['category'],
                'priority' => $alert['priority'],
                'person_id' => $alert['person_id'],
                'entity_type' => $alert['entity_type'],
                'entity_id' => $alert['entity_id'],
                'data' => json_encode($alert['data']),
                'status' => 'pending',
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to store alert: {$e->getMessage()}");
        }
    }

    /**
     * Fire webhook for alert
     */
    protected function fireWebhook(array $alert): void
    {
        $category = $alert['category'];
        $endpoints = $this->webhookEndpoints[$category] ?? $this->webhookEndpoints['all'] ?? [];

        foreach ($endpoints as $endpoint) {
            try {
                // Queue webhook delivery
                dispatch(function () use ($endpoint, $alert) {
                    $client = new \GuzzleHttp\Client(['timeout' => 5]);
                    $client->post($endpoint['url'], [
                        'json' => $alert,
                        'headers' => [
                            'X-Webhook-Secret' => $endpoint['secret'] ?? '',
                            'X-Alert-Type' => $alert['type'],
                            'X-Alert-Priority' => $alert['priority'],
                        ],
                    ]);
                })->onQueue('webhooks');
            } catch (\Exception $e) {
                Log::warning("Failed to queue webhook: {$e->getMessage()}");
            }
        }
    }

    /**
     * Load webhook endpoints from config
     */
    protected function loadWebhookEndpoints(): void
    {
        $endpoints = DB::table('webhook_endpoints')
            ->where('tenant_id', $this->tenantId)
            ->where('active', true)
            ->where('event_type', 'like', 'alert.%')
            ->get();

        foreach ($endpoints as $endpoint) {
            $category = str_replace('alert.', '', $endpoint->event_type);
            $this->webhookEndpoints[$category][] = [
                'url' => $endpoint->url,
                'secret' => $endpoint->secret,
            ];
        }
    }

    /**
     * Check if alert type is on cooldown for entity
     */
    protected function isOnCooldown(string $type, int $entityId, string $entityType = 'person'): bool
    {
        $key = $this->getCooldownKey($type, $entityId, $entityType);
        return Cache::has($key);
    }

    protected function getCooldownKey(string $type, int $entityId, string $entityType): string
    {
        return "alert_cooldown:{$this->tenantId}:{$type}:{$entityType}:{$entityId}";
    }

    /**
     * Get recent event views count
     */
    protected function getRecentEventViews(int $personId, int $eventId, int $hours): int
    {
        return DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->where('event_id', $eventId)
            ->where('event_type', 'view_item')
            ->where('created_at', '>', now()->subHours($hours))
            ->count();
    }

    /**
     * Check if person has purchased event
     */
    protected function hasPurchasedEvent(int $personId, int $eventId): bool
    {
        return DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->where('event_id', $eventId)
            ->where('event_type', 'purchase')
            ->exists();
    }

    /**
     * Get pending alerts
     */
    public function getPendingAlerts(int $limit = 50): Collection
    {
        return DB::table('tracking_alerts')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'pending')
            ->orderByRaw("CASE priority
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark alert as handled
     */
    public function markAsHandled(string $alertId, ?string $action = null): bool
    {
        return DB::table('tracking_alerts')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $alertId)
            ->update([
                'status' => 'handled',
                'handled_at' => now(),
                'action_taken' => $action,
            ]) > 0;
    }

    /**
     * Get alert statistics
     */
    public function getAlertStats(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $stats = DB::table('tracking_alerts')
            ->where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                type,
                category,
                priority,
                COUNT(*) as count,
                SUM(CASE WHEN status = "handled" THEN 1 ELSE 0 END) as handled_count
            ')
            ->groupBy('type', 'category', 'priority')
            ->get();

        return [
            'period_days' => $days,
            'by_type' => $stats->groupBy('type')->map(fn($g) => $g->sum('count')),
            'by_category' => $stats->groupBy('category')->map(fn($g) => $g->sum('count')),
            'by_priority' => $stats->groupBy('priority')->map(fn($g) => $g->sum('count')),
            'total' => $stats->sum('count'),
            'handled' => $stats->sum('handled_count'),
            'handle_rate' => $stats->sum('count') > 0
                ? round($stats->sum('handled_count') / $stats->sum('count') * 100, 1)
                : 0,
        ];
    }
}
