<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\MarketplaceCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class BuyerJourneyService
{
    /**
     * Get complete buyer journey for an order
     */
    public function getOrderJourney(Order $order): array
    {
        $customerId = $order->marketplace_customer_id;
        $eventId = $order->marketplace_event_id;

        if (!$customerId || !$eventId) {
            return $this->getMinimalJourney($order);
        }

        // Get the customer
        $customer = MarketplaceCustomer::find($customerId);

        // Get all events for this customer related to this event
        $events = CoreCustomerEvent::where('event_id', $eventId)
            ->where(function ($q) use ($customerId, $order) {
                $q->where('customer_id', $customerId)
                  ->orWhere('order_id', $order->id);
            })
            ->orderBy('created_at')
            ->get();

        // Build journey timeline
        $journey = $this->buildJourneyTimeline($events, $order);

        // Get customer stats
        $customerStats = $this->getCustomerStats($customer, $order);

        // Get device and location info from first event
        $firstEvent = $events->first();

        return [
            'customer' => [
                'id' => $customerId,
                'name' => $this->maskName($customer?->name ?? $order->customer_name),
                'email' => $this->maskEmail($order->customer_email),
                'initials' => $this->getInitials($customer?->name ?? $order->customer_name),
                'is_returning' => $customerStats['is_returning'],
                'previous_purchases' => $customerStats['previous_purchases'],
                'lifetime_value' => $customerStats['lifetime_value'],
            ],
            'purchase' => [
                'order_id' => $order->id,
                'date' => $order->paid_at?->format('M d, Y H:i'),
                'amount' => $order->total,
                'quantity' => $order->tickets()->count(),
                'ticket_type' => $order->tickets()->first()?->ticketType?->name ?? 'General',
            ],
            'source' => [
                'channel' => $this->determineSourceChannel($events),
                'medium' => $firstEvent?->utm_medium,
                'campaign' => $firstEvent?->utm_campaign,
                'referrer' => $firstEvent?->referrer,
            ],
            'device' => [
                'type' => ucfirst($firstEvent?->device_type ?? 'Desktop'),
                'browser' => $firstEvent?->browser ?? 'Unknown',
                'os' => $firstEvent?->os ?? 'Unknown',
            ],
            'location' => [
                'city' => $firstEvent?->city ?? 'Unknown',
                'country' => $firstEvent?->country_code ?? 'Unknown',
                'flag' => $this->getCountryFlag($firstEvent?->country_code),
            ],
            'timing' => [
                'first_visit' => $events->first()?->created_at?->format('M d, Y H:i'),
                'purchase_date' => $order->paid_at?->format('M d, Y H:i'),
                'time_to_purchase' => $this->calculateTimeToPurchase($events, $order),
                'sessions_count' => $events->unique('session_id')->count(),
            ],
            'journey' => $journey,
            'payment' => [
                'method' => $order->payment_processor ?? 'Card',
                'icon' => $this->getPaymentIcon($order->payment_processor),
                'status' => $order->payment_status ?? 'completed',
            ],
        ];
    }

    /**
     * Build journey timeline from events
     */
    protected function buildJourneyTimeline(Collection $events, Order $order): array
    {
        $journey = [];
        $lastSessionId = null;

        foreach ($events as $event) {
            // Add session separator if new session
            if ($event->session_id !== $lastSessionId && $lastSessionId !== null) {
                $journey[] = [
                    'type' => 'session_break',
                    'time' => $event->created_at->format('H:i'),
                    'description' => 'New session started',
                ];
            }
            $lastSessionId = $event->session_id;

            $journeyEvent = $this->formatJourneyEvent($event);
            if ($journeyEvent) {
                $journey[] = $journeyEvent;
            }
        }

        // Add purchase event if not already in journey
        $hasPurchase = collect($journey)->contains('type', 'purchase');
        if (!$hasPurchase && $order->paid_at) {
            $journey[] = [
                'type' => 'purchase',
                'time' => $order->paid_at->format('H:i'),
                'description' => 'Completed purchase',
                'amount' => $order->total,
            ];
        }

        return $journey;
    }

    /**
     * Format a single journey event
     */
    protected function formatJourneyEvent(CoreCustomerEvent $event): ?array
    {
        $formatted = [
            'time' => $event->created_at->format('H:i'),
        ];

        switch ($event->event_type) {
            case CoreCustomerEvent::TYPE_PAGE_VIEW:
                // Check if this is the first page view (found)
                $formatted['type'] = 'pageview';
                $formatted['page'] = $event->page_path ?? '/';
                $formatted['description'] = $event->page_title ?? 'Viewed page';

                // If this is first event and has source, mark as "found"
                if ($event->utm_source || $event->referrer || $event->gclid || $event->fbclid) {
                    return [
                        'type' => 'found',
                        'time' => $event->created_at->format('H:i'),
                        'source' => $this->formatSource($event),
                        'description' => 'Found via ' . $this->formatSource($event),
                    ];
                }
                break;

            case CoreCustomerEvent::TYPE_VIEW_ITEM:
                $formatted['type'] = 'event';
                $formatted['name'] = 'view_pricing';
                $formatted['description'] = 'Viewed pricing';
                break;

            case CoreCustomerEvent::TYPE_ADD_TO_CART:
                $formatted['type'] = 'event';
                $formatted['name'] = 'add_to_cart';
                $formatted['params'] = ($event->content_name ?? 'Tickets') . ' x' . ($event->quantity ?? 1);
                $formatted['description'] = 'Added to cart: ' . ($event->content_name ?? 'tickets');
                break;

            case CoreCustomerEvent::TYPE_BEGIN_CHECKOUT:
                $formatted['type'] = 'pageview';
                $formatted['page'] = '/checkout';
                $formatted['description'] = 'Started checkout';
                break;

            case CoreCustomerEvent::TYPE_PURCHASE:
                $formatted['type'] = 'purchase';
                $formatted['amount'] = $event->event_value;
                $formatted['description'] = 'Completed purchase';
                break;

            case CoreCustomerEvent::TYPE_SEARCH:
                $formatted['type'] = 'event';
                $formatted['name'] = 'search';
                $formatted['params'] = $event->event_label;
                $formatted['description'] = 'Searched: ' . $event->event_label;
                break;

            case CoreCustomerEvent::TYPE_SCROLL:
                // Skip scroll events in journey
                return null;

            default:
                // For custom events
                if ($event->event_action) {
                    $formatted['type'] = 'event';
                    $formatted['name'] = $event->event_action;
                    $formatted['description'] = ucfirst(str_replace('_', ' ', $event->event_action));
                } else {
                    return null;
                }
        }

        return $formatted;
    }

    /**
     * Get minimal journey when no tracking data available
     */
    protected function getMinimalJourney(Order $order): array
    {
        return [
            'customer' => [
                'name' => $this->maskName($order->customer_name ?? 'Anonymous'),
                'email' => $this->maskEmail($order->customer_email ?? ''),
                'initials' => $this->getInitials($order->customer_name ?? 'AN'),
                'is_returning' => false,
            ],
            'purchase' => [
                'order_id' => $order->id,
                'date' => $order->paid_at?->format('M d, Y H:i'),
                'amount' => $order->total,
                'quantity' => $order->tickets()->count(),
                'ticket_type' => $order->tickets()->first()?->ticketType?->name ?? 'General',
            ],
            'source' => [
                'channel' => $order->source ?? 'Direct',
            ],
            'device' => [
                'type' => 'Unknown',
            ],
            'location' => [
                'city' => 'Unknown',
            ],
            'timing' => [
                'purchase_date' => $order->paid_at?->format('M d, Y H:i'),
            ],
            'journey' => [
                [
                    'type' => 'purchase',
                    'time' => $order->paid_at?->format('H:i'),
                    'description' => 'Completed purchase',
                    'amount' => $order->total,
                ],
            ],
            'payment' => [
                'method' => $order->payment_processor ?? 'Card',
                'icon' => $this->getPaymentIcon($order->payment_processor),
            ],
        ];
    }

    /**
     * Get customer statistics
     */
    protected function getCustomerStats(?MarketplaceCustomer $customer, Order $currentOrder): array
    {
        if (!$customer) {
            return [
                'is_returning' => false,
                'previous_purchases' => 0,
                'lifetime_value' => $currentOrder->total,
            ];
        }

        $previousOrders = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('id', '!=', $currentOrder->id)
            ->get();

        return [
            'is_returning' => $previousOrders->count() > 0,
            'previous_purchases' => $previousOrders->count(),
            'lifetime_value' => $previousOrders->sum('total') + $currentOrder->total,
        ];
    }

    /**
     * Calculate time from first visit to purchase
     */
    protected function calculateTimeToPurchase(Collection $events, Order $order): string
    {
        $firstEvent = $events->first();
        $purchaseTime = $order->paid_at;

        if (!$firstEvent || !$purchaseTime) {
            return 'N/A';
        }

        $firstVisit = $firstEvent->created_at;
        $diff = $firstVisit->diff($purchaseTime);

        if ($diff->days > 0) {
            return $diff->days . 'd ' . $diff->h . 'h';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        } else {
            return $diff->i . 'm ' . $diff->s . 's';
        }
    }

    /**
     * Determine source channel from events
     */
    protected function determineSourceChannel(Collection $events): string
    {
        $firstEvent = $events->first();

        if (!$firstEvent) {
            return 'Direct';
        }

        // Check click IDs first
        if ($firstEvent->fbclid) return 'Facebook Ads';
        if ($firstEvent->gclid) return 'Google Ads';
        if ($firstEvent->ttclid) return 'TikTok Ads';

        // Check UTM source
        if ($firstEvent->utm_source) {
            return match (strtolower($firstEvent->utm_source)) {
                'facebook', 'fb' => 'Facebook',
                'instagram', 'ig' => 'Instagram',
                'google' => $firstEvent->utm_medium === 'cpc' ? 'Google Ads' : 'Google',
                'tiktok' => 'TikTok',
                'email', 'newsletter' => 'Email',
                'twitter', 'x' => 'Twitter/X',
                default => ucfirst($firstEvent->utm_source),
            };
        }

        // Check referrer
        if ($firstEvent->referrer) {
            $host = parse_url($firstEvent->referrer, PHP_URL_HOST);
            if ($host) {
                if (str_contains($host, 'facebook')) return 'Facebook';
                if (str_contains($host, 'instagram')) return 'Instagram';
                if (str_contains($host, 'google')) return 'Google';
                if (str_contains($host, 'tiktok')) return 'TikTok';
                return 'Organic';
            }
        }

        return 'Direct';
    }

    /**
     * Format source for display
     */
    protected function formatSource(CoreCustomerEvent $event): string
    {
        if ($event->fbclid) return 'Facebook Ad';
        if ($event->gclid) return 'Google Ad';
        if ($event->ttclid) return 'TikTok Ad';

        if ($event->utm_source) {
            $source = ucfirst($event->utm_source);
            if ($event->utm_campaign) {
                return "{$source} ({$event->utm_campaign})";
            }
            return $source;
        }

        if ($event->referrer) {
            $host = parse_url($event->referrer, PHP_URL_HOST);
            return $host ?: 'Referral';
        }

        return 'Direct';
    }

    /**
     * Get multiple buyer journeys for recent orders
     */
    public function getRecentBuyerJourneys(int $eventId, int $limit = 10): array
    {
        $orders = Order::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->orderBy('paid_at', 'desc')
            ->limit($limit)
            ->get();

        return $orders->map(fn($order) => $this->getOrderJourney($order))->toArray();
    }

    /**
     * Get journey analytics summary
     */
    public function getJourneyAnalytics(int $eventId): array
    {
        // Get all purchase events for this event
        $purchaseEvents = CoreCustomerEvent::where('event_id', $eventId)
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->get();

        if ($purchaseEvents->isEmpty()) {
            return [
                'avg_touchpoints' => 0,
                'avg_time_to_purchase' => 'N/A',
                'top_entry_sources' => [],
                'common_paths' => [],
            ];
        }

        // Calculate average touchpoints
        $totalTouchpoints = 0;
        $journeyCount = 0;
        $entrySourceCounts = [];

        foreach ($purchaseEvents as $purchase) {
            $sessionEvents = CoreCustomerEvent::where('session_id', $purchase->session_id)
                ->where('event_id', $eventId)
                ->count();

            $totalTouchpoints += $sessionEvents;
            $journeyCount++;

            // Track entry sources
            $firstEvent = CoreCustomerEvent::where('session_id', $purchase->session_id)
                ->where('event_id', $eventId)
                ->orderBy('created_at')
                ->first();

            if ($firstEvent) {
                $source = $this->formatSource($firstEvent);
                $entrySourceCounts[$source] = ($entrySourceCounts[$source] ?? 0) + 1;
            }
        }

        // Sort entry sources
        arsort($entrySourceCounts);
        $topEntrySources = array_slice($entrySourceCounts, 0, 5, true);

        return [
            'avg_touchpoints' => $journeyCount > 0 ? round($totalTouchpoints / $journeyCount, 1) : 0,
            'total_journeys' => $journeyCount,
            'top_entry_sources' => array_map(fn($source, $count) => [
                'source' => $source,
                'count' => $count,
                'percent' => $journeyCount > 0 ? round(($count / $journeyCount) * 100, 1) : 0,
            ], array_keys($topEntrySources), array_values($topEntrySources)),
        ];
    }

    /* Helper methods */

    protected function maskName(?string $name): string
    {
        if (!$name) return 'Anonymous';

        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return $parts[0] . ' ' . substr($parts[1], 0, 1) . '.';
        }
        return substr($name, 0, 3) . '***';
    }

    protected function maskEmail(?string $email): string
    {
        if (!$email || !str_contains($email, '@')) return '';

        $parts = explode('@', $email);
        return substr($parts[0], 0, 1) . '***@' . $parts[1];
    }

    protected function getInitials(?string $name): string
    {
        if (!$name) return 'AN';

        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    protected function getCountryFlag(?string $countryCode): string
    {
        $flags = [
            'RO' => '🇷🇴', 'HU' => '🇭🇺', 'AT' => '🇦🇹', 'DE' => '🇩🇪',
            'GB' => '🇬🇧', 'FR' => '🇫🇷', 'IT' => '🇮🇹', 'ES' => '🇪🇸',
            'NL' => '🇳🇱', 'BE' => '🇧🇪', 'PL' => '🇵🇱', 'CZ' => '🇨🇿',
            'BG' => '🇧🇬', 'MD' => '🇲🇩', 'UA' => '🇺🇦', 'RS' => '🇷🇸',
            'US' => '🇺🇸', 'CA' => '🇨🇦', 'AU' => '🇦🇺',
        ];

        return $flags[$countryCode ?? ''] ?? '🌍';
    }

    protected function getPaymentIcon(?string $processor): string
    {
        return match (strtolower($processor ?? '')) {
            'stripe', 'card' => '💳',
            'apple_pay' => '🍎',
            'google_pay' => '🔵',
            'paypal' => '💙',
            'bank_transfer' => '🏦',
            default => '💳',
        };
    }
}
