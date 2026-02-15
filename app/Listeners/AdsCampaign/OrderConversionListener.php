<?php

namespace App\Listeners\AdsCampaign;

use App\Events\OrderConfirmed;
use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignMetric;
use App\Models\Order;
use App\Models\Platform\CoreSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Automatically attributes confirmed orders to ad campaigns via UTM parameters.
 *
 * Attribution flow:
 * 1. Order confirmed → find order by ref
 * 2. Check order meta/metadata for utm_campaign
 * 3. If not found, check CoreSession for the customer's last session with campaign UTM
 * 4. Match utm_campaign to AdsCampaign.utm_campaign
 * 5. Record conversion + revenue on today's metric snapshot
 * 6. Recalculate campaign aggregates
 */
class OrderConversionListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(OrderConfirmed $event): void
    {
        try {
            $order = Order::where('order_number', $event->orderRef)
                ->orWhere('id', $event->orderRef)
                ->first();

            if (!$order) {
                Log::debug('OrderConversionListener: Order not found', [
                    'order_ref' => $event->orderRef,
                ]);
                return;
            }

            $attribution = $this->resolveAttribution($order, $event->orderData);

            if (!$attribution) {
                return;
            }

            $campaign = AdsCampaign::where('utm_campaign', $attribution['utm_campaign'])
                ->whereIn('status', ['active', 'completed'])
                ->first();

            if (!$campaign) {
                return;
            }

            $this->recordConversion($campaign, $order, $attribution);

            Log::info('OrderConversionListener: Conversion attributed', [
                'order_id' => $order->id,
                'campaign_id' => $campaign->id,
                'utm_campaign' => $attribution['utm_campaign'],
                'revenue' => $order->total ?? ($order->total_cents / 100),
                'platform' => $attribution['platform'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            Log::error('OrderConversionListener: Failed to attribute conversion', [
                'order_ref' => $event->orderRef,
                'tenant_id' => $event->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve UTM attribution from multiple sources, in priority order.
     */
    protected function resolveAttribution(Order $order, array $orderData): ?array
    {
        // 1. Check orderData context (may contain UTM from checkout)
        if (!empty($orderData['utm_campaign'])) {
            return [
                'utm_campaign' => $orderData['utm_campaign'],
                'utm_source' => $orderData['utm_source'] ?? null,
                'utm_medium' => $orderData['utm_medium'] ?? null,
                'platform' => $this->detectPlatformFromUtm($orderData),
                'source' => 'order_data',
            ];
        }

        // 2. Check order meta JSON field
        $meta = $order->meta ?? [];
        if (!empty($meta['utm_campaign'])) {
            return [
                'utm_campaign' => $meta['utm_campaign'],
                'utm_source' => $meta['utm_source'] ?? null,
                'utm_medium' => $meta['utm_medium'] ?? null,
                'platform' => $this->detectPlatformFromUtm($meta),
                'source' => 'order_meta',
            ];
        }

        // 3. Check order metadata JSON field
        $metadata = $order->metadata ?? [];
        if (!empty($metadata['utm_campaign'])) {
            return [
                'utm_campaign' => $metadata['utm_campaign'],
                'utm_source' => $metadata['utm_source'] ?? null,
                'utm_medium' => $metadata['utm_medium'] ?? null,
                'platform' => $this->detectPlatformFromUtm($metadata),
                'source' => 'order_metadata',
            ];
        }

        // 4. Check CoreSession for last session with utm_campaign (within 30-day window)
        if ($order->customer_email) {
            $session = CoreSession::where(function ($q) use ($order) {
                $q->where('customer_id', $order->customer_id)
                    ->orWhereHas('customer', function ($q2) use ($order) {
                        $q2->where('email', $order->customer_email);
                    });
            })
                ->whereNotNull('utm_campaign')
                ->where('started_at', '>=', now()->subDays(30))
                ->where('converted', true)
                ->orderBy('started_at', 'desc')
                ->first();

            if ($session) {
                return [
                    'utm_campaign' => $session->utm_campaign,
                    'utm_source' => $session->utm_source,
                    'utm_medium' => $session->utm_medium,
                    'platform' => $this->detectPlatformFromSession($session),
                    'source' => 'session_attribution',
                ];
            }
        }

        // 5. Check click IDs (gclid = Google, fbclid = Facebook)
        if ($order->customer_email) {
            $session = CoreSession::where(function ($q) use ($order) {
                $q->where('customer_id', $order->customer_id)
                    ->orWhereHas('customer', function ($q2) use ($order) {
                        $q2->where('email', $order->customer_email);
                    });
            })
                ->where(function ($q) {
                    $q->whereNotNull('gclid')
                        ->orWhereNotNull('fbclid');
                })
                ->where('started_at', '>=', now()->subDays(7))
                ->orderBy('started_at', 'desc')
                ->first();

            if ($session) {
                // Try to match a campaign by tenant + active status
                $platform = $session->gclid ? 'google' : 'facebook';
                $activeCampaign = AdsCampaign::where('tenant_id', $order->tenant_id)
                    ->whereIn('status', ['active', 'completed'])
                    ->whereJsonContains('target_platforms', $platform)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if ($activeCampaign) {
                    return [
                        'utm_campaign' => $activeCampaign->utm_campaign,
                        'utm_source' => $platform,
                        'utm_medium' => 'cpc',
                        'platform' => $platform,
                        'source' => 'click_id_attribution',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Record the conversion on today's metric snapshot.
     */
    protected function recordConversion(AdsCampaign $campaign, Order $order, array $attribution): void
    {
        $revenue = (float) ($order->total ?? ($order->total_cents / 100));
        $platform = $attribution['platform'] ?? 'aggregated';
        $today = now()->toDateString();

        $metric = AdsCampaignMetric::firstOrCreate(
            [
                'campaign_id' => $campaign->id,
                'date' => $today,
                'platform' => $platform,
            ],
            [
                'impressions' => 0,
                'reach' => 0,
                'clicks' => 0,
                'ctr' => 0,
                'spend' => 0,
            ]
        );

        $metric->increment('conversions');
        $metric->increment('revenue', $revenue);
        $metric->increment('tickets_sold', max(1, $order->tickets()->count()));

        // Check if this is a first-time buyer for this campaign's tenant
        $previousOrders = Order::where('customer_email', $order->customer_email)
            ->where('tenant_id', $order->tenant_id)
            ->where('id', '!=', $order->id)
            ->where('status', 'confirmed')
            ->exists();

        if (!$previousOrders) {
            $metric->increment('new_customers');
        }

        // Recalculate derived metrics
        $metric->refresh();
        if ($metric->conversions > 0 && $metric->clicks > 0) {
            $metric->conversion_rate = ($metric->conversions / $metric->clicks) * 100;
        }
        if ($metric->conversions > 0 && $metric->spend > 0) {
            $metric->cost_per_conversion = $metric->spend / $metric->conversions;
        }
        if ($metric->spend > 0) {
            $metric->roas = $metric->revenue / $metric->spend;
        }
        if ($metric->conversions > 0 && $metric->revenue > 0) {
            $metric->cac = $metric->spend / $metric->conversions;
        }
        $metric->save();

        // Also update aggregated row
        if ($platform !== 'aggregated') {
            $aggMetric = AdsCampaignMetric::firstOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'date' => $today,
                    'platform' => 'aggregated',
                ],
                [
                    'impressions' => 0,
                    'reach' => 0,
                    'clicks' => 0,
                    'ctr' => 0,
                    'spend' => 0,
                ]
            );

            $aggMetric->increment('conversions');
            $aggMetric->increment('revenue', $revenue);
            $aggMetric->increment('tickets_sold', max(1, $order->tickets()->count()));
            if (!$previousOrders) {
                $aggMetric->increment('new_customers');
            }

            $aggMetric->refresh();
            if ($aggMetric->conversions > 0 && $aggMetric->clicks > 0) {
                $aggMetric->conversion_rate = ($aggMetric->conversions / $aggMetric->clicks) * 100;
            }
            if ($aggMetric->spend > 0) {
                $aggMetric->roas = $aggMetric->revenue / $aggMetric->spend;
            }
            $aggMetric->save();
        }

        // Recalculate campaign-level aggregates
        $campaign->recalculateAggregates();
    }

    protected function detectPlatformFromUtm(array $data): ?string
    {
        $source = strtolower($data['utm_source'] ?? '');

        if (str_contains($source, 'facebook') || str_contains($source, 'fb') || str_contains($source, 'meta')) {
            return 'facebook';
        }
        if (str_contains($source, 'instagram') || str_contains($source, 'ig')) {
            return 'instagram';
        }
        if (str_contains($source, 'google') || str_contains($source, 'gads')) {
            return 'google';
        }

        // Check for click IDs
        if (!empty($data['fbclid'])) return 'facebook';
        if (!empty($data['gclid'])) return 'google';

        return null;
    }

    protected function detectPlatformFromSession(CoreSession $session): ?string
    {
        if ($session->fbclid) return 'facebook';
        if ($session->gclid) return 'google';

        return $this->detectPlatformFromUtm([
            'utm_source' => $session->utm_source,
        ]);
    }
}
