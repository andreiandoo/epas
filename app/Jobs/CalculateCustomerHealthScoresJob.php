<?php

namespace App\Jobs;

use App\Models\Platform\CoreCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateCustomerHealthScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    /**
     * Health score weights
     */
    protected array $weights = [
        'rfm' => 0.35,           // RFM score weight
        'engagement' => 0.25,    // Engagement score weight
        'recency' => 0.20,       // Purchase recency weight
        'churn_risk' => 0.20,    // Inverse churn risk weight
    ];

    public function handle(): void
    {
        Log::info('Starting customer health score calculation');

        $processed = 0;
        $errors = 0;

        CoreCustomer::query()
            ->whereNotNull('first_seen_at')
            ->where('is_merged', false)
            ->chunkById(500, function ($customers) use (&$processed, &$errors) {
                foreach ($customers as $customer) {
                    try {
                        $this->calculateHealthScore($customer);
                        $processed++;
                    } catch (\Exception $e) {
                        $errors++;
                        Log::warning('Failed to calculate health score for customer', [
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('Completed customer health score calculation', [
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }

    protected function calculateHealthScore(CoreCustomer $customer): void
    {
        $breakdown = [];

        // 1. RFM Score Component (0-100)
        $rfmScore = $this->calculateRfmComponent($customer);
        $breakdown['rfm'] = [
            'score' => $rfmScore,
            'weight' => $this->weights['rfm'],
            'weighted' => $rfmScore * $this->weights['rfm'],
        ];

        // 2. Engagement Score Component (0-100)
        $engagementScore = $this->calculateEngagementComponent($customer);
        $breakdown['engagement'] = [
            'score' => $engagementScore,
            'weight' => $this->weights['engagement'],
            'weighted' => $engagementScore * $this->weights['engagement'],
        ];

        // 3. Recency Score Component (0-100)
        $recencyScore = $this->calculateRecencyComponent($customer);
        $breakdown['recency'] = [
            'score' => $recencyScore,
            'weight' => $this->weights['recency'],
            'weighted' => $recencyScore * $this->weights['recency'],
        ];

        // 4. Churn Risk Component (inverse, 0-100)
        $churnScore = $this->calculateChurnComponent($customer);
        $breakdown['churn_risk'] = [
            'score' => $churnScore,
            'weight' => $this->weights['churn_risk'],
            'weighted' => $churnScore * $this->weights['churn_risk'],
        ];

        // Calculate overall health score
        $healthScore = (int) round(
            $breakdown['rfm']['weighted'] +
            $breakdown['engagement']['weighted'] +
            $breakdown['recency']['weighted'] +
            $breakdown['churn_risk']['weighted']
        );

        // Ensure score is within bounds
        $healthScore = max(0, min(100, $healthScore));

        // Update customer with segment assignment
        $customer->update([
            'health_score' => $healthScore,
            'health_score_breakdown' => $breakdown,
            'health_score_calculated_at' => now(),
            'customer_segment' => $this->determineSegment($healthScore, $customer),
        ]);
    }

    protected function calculateRfmComponent(CoreCustomer $customer): int
    {
        // If RFM scores are calculated, use them
        if ($customer->rfm_recency_score && $customer->rfm_frequency_score && $customer->rfm_monetary_score) {
            // Convert 1-5 scale to 0-100
            $avgRfm = ($customer->rfm_recency_score + $customer->rfm_frequency_score + $customer->rfm_monetary_score) / 3;
            return (int) round(($avgRfm - 1) / 4 * 100);
        }

        // Calculate basic RFM if not pre-calculated
        $score = 0;

        // Recency (based on last purchase)
        if ($customer->last_purchase_at) {
            $daysSincePurchase = $customer->last_purchase_at->diffInDays(now());
            if ($daysSincePurchase <= 30) $score += 33;
            elseif ($daysSincePurchase <= 90) $score += 22;
            elseif ($daysSincePurchase <= 180) $score += 11;
        }

        // Frequency
        if ($customer->total_orders >= 10) $score += 33;
        elseif ($customer->total_orders >= 5) $score += 26;
        elseif ($customer->total_orders >= 3) $score += 20;
        elseif ($customer->total_orders >= 1) $score += 13;

        // Monetary
        if ($customer->total_spent >= 1000) $score += 34;
        elseif ($customer->total_spent >= 500) $score += 27;
        elseif ($customer->total_spent >= 200) $score += 20;
        elseif ($customer->total_spent >= 50) $score += 13;

        return min(100, $score);
    }

    protected function calculateEngagementComponent(CoreCustomer $customer): int
    {
        // Use existing engagement score if available
        if ($customer->engagement_score > 0) {
            return min(100, $customer->engagement_score);
        }

        $score = 0;

        // Visit frequency
        if ($customer->total_visits >= 50) $score += 25;
        elseif ($customer->total_visits >= 20) $score += 20;
        elseif ($customer->total_visits >= 10) $score += 15;
        elseif ($customer->total_visits >= 5) $score += 10;
        elseif ($customer->total_visits >= 1) $score += 5;

        // Page views per visit
        if ($customer->total_visits > 0) {
            $pagesPerVisit = $customer->total_pageviews / $customer->total_visits;
            if ($pagesPerVisit >= 5) $score += 25;
            elseif ($pagesPerVisit >= 3) $score += 20;
            elseif ($pagesPerVisit >= 2) $score += 15;
            elseif ($pagesPerVisit >= 1) $score += 10;
        }

        // Email engagement
        if ($customer->email_open_rate >= 50) $score += 25;
        elseif ($customer->email_open_rate >= 30) $score += 20;
        elseif ($customer->email_open_rate >= 15) $score += 15;
        elseif ($customer->email_subscribed) $score += 10;

        // Recency of last visit
        if ($customer->last_seen_at) {
            $daysSinceVisit = $customer->last_seen_at->diffInDays(now());
            if ($daysSinceVisit <= 7) $score += 25;
            elseif ($daysSinceVisit <= 30) $score += 20;
            elseif ($daysSinceVisit <= 90) $score += 10;
        }

        return min(100, $score);
    }

    protected function calculateRecencyComponent(CoreCustomer $customer): int
    {
        // Based on last activity (purchase or visit)
        $lastActivity = $customer->last_purchase_at ?? $customer->last_seen_at;

        if (!$lastActivity) {
            return 0;
        }

        $daysSince = $lastActivity->diffInDays(now());

        // Score decreases as time passes
        if ($daysSince <= 7) return 100;
        if ($daysSince <= 14) return 90;
        if ($daysSince <= 30) return 75;
        if ($daysSince <= 60) return 60;
        if ($daysSince <= 90) return 45;
        if ($daysSince <= 180) return 30;
        if ($daysSince <= 365) return 15;

        return 5;
    }

    protected function calculateChurnComponent(CoreCustomer $customer): int
    {
        // Inverse of churn risk (high churn risk = low health score)
        if ($customer->churn_risk_score > 0) {
            return 100 - min(100, $customer->churn_risk_score);
        }

        // Calculate basic churn indicators
        $churnRisk = 0;

        // No recent activity
        if ($customer->last_seen_at && $customer->last_seen_at->diffInDays(now()) > 90) {
            $churnRisk += 30;
        }

        // No recent purchase for existing customers
        if ($customer->total_orders > 0 && $customer->last_purchase_at) {
            $daysSincePurchase = $customer->last_purchase_at->diffInDays(now());
            $avgPurchaseInterval = $customer->purchase_frequency_days ?? 90;

            if ($daysSincePurchase > $avgPurchaseInterval * 2) {
                $churnRisk += 40;
            } elseif ($daysSincePurchase > $avgPurchaseInterval * 1.5) {
                $churnRisk += 20;
            }
        }

        // Unsubscribed from email
        if (!$customer->email_subscribed && $customer->email_unsubscribed_at) {
            $churnRisk += 15;
        }

        // Declining engagement (cart abandoned without purchase)
        if ($customer->has_cart_abandoned ?? false) {
            $churnRisk += 15;
        }

        return 100 - min(100, $churnRisk);
    }

    protected function determineSegment(int $healthScore, CoreCustomer $customer): string
    {
        // Combine health score with purchase history for segmentation
        $hasPurchased = $customer->total_orders > 0;
        $isHighValue = $customer->total_spent >= 500;
        $isRecent = $customer->last_purchase_at && $customer->last_purchase_at->diffInDays(now()) <= 90;

        if ($healthScore >= 80) {
            if ($isHighValue && $hasPurchased) {
                return 'VIP';
            }
            return 'Champions';
        }

        if ($healthScore >= 60) {
            if ($hasPurchased && $isRecent) {
                return 'Loyal';
            }
            return 'Promising';
        }

        if ($healthScore >= 40) {
            if ($hasPurchased && !$isRecent) {
                return 'At Risk';
            }
            return 'Needs Attention';
        }

        if ($healthScore >= 20) {
            if ($hasPurchased) {
                return 'Hibernating';
            }
            return 'About to Sleep';
        }

        if ($hasPurchased) {
            return 'Lost';
        }

        return 'New';
    }
}
