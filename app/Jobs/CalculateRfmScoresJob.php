<?php

namespace App\Jobs;

use App\Models\Platform\CoreCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CalculateRfmScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * The batch size for processing customers.
     */
    protected int $batchSize = 500;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting RFM score calculation');

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'errors' => 0,
            'segments' => [],
        ];

        try {
            // Process customers in batches
            CoreCustomer::query()
                ->where('total_orders', '>', 0) // Only calculate for purchasers
                ->chunkById($this->batchSize, function ($customers) use (&$stats) {
                    foreach ($customers as $customer) {
                        try {
                            $this->calculateScores($customer);
                            $stats['processed']++;
                            $stats['updated']++;

                            // Track segment distribution
                            $segment = $customer->rfm_segment ?? 'Other';
                            $stats['segments'][$segment] = ($stats['segments'][$segment] ?? 0) + 1;

                        } catch (\Exception $e) {
                            $stats['errors']++;
                            Log::warning('Failed to calculate RFM for customer', [
                                'customer_id' => $customer->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });

            // Also update days_since_last_purchase for all customers
            $this->updateDaysSinceLastPurchase();

            // Update customer segments based on RFM
            $this->updateCustomerSegments();

            Log::info('RFM score calculation completed', $stats);

        } catch (\Exception $e) {
            Log::error('RFM calculation job failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);

            throw $e;
        }
    }

    /**
     * Calculate RFM scores for a single customer.
     */
    protected function calculateScores(CoreCustomer $customer): void
    {
        // Get current percentile thresholds for relative scoring
        // This makes scoring relative to your customer base

        // Recency (days since last purchase) - lower is better
        $recency = $customer->last_purchase_at
            ? $customer->last_purchase_at->diffInDays(now())
            : 365;

        // Score based on recency (1-5, where 5 is best)
        if ($recency <= 14) {
            $recencyScore = 5;
        } elseif ($recency <= 30) {
            $recencyScore = 4;
        } elseif ($recency <= 60) {
            $recencyScore = 3;
        } elseif ($recency <= 90) {
            $recencyScore = 2;
        } else {
            $recencyScore = 1;
        }

        // Frequency (number of orders) - higher is better
        $frequency = $customer->total_orders ?? 0;

        if ($frequency >= 10) {
            $frequencyScore = 5;
        } elseif ($frequency >= 5) {
            $frequencyScore = 4;
        } elseif ($frequency >= 3) {
            $frequencyScore = 3;
        } elseif ($frequency >= 2) {
            $frequencyScore = 2;
        } else {
            $frequencyScore = 1;
        }

        // Monetary (total spent) - higher is better
        $monetary = $customer->total_spent ?? 0;

        if ($monetary >= 1000) {
            $monetaryScore = 5;
        } elseif ($monetary >= 500) {
            $monetaryScore = 4;
        } elseif ($monetary >= 200) {
            $monetaryScore = 3;
        } elseif ($monetary >= 50) {
            $monetaryScore = 2;
        } else {
            $monetaryScore = 1;
        }

        // Determine RFM segment based on score combination
        $rfmCode = "{$recencyScore}{$frequencyScore}{$monetaryScore}";
        $segment = $this->determineSegment($rfmCode);

        // Calculate composite RFM score (useful for sorting)
        $compositeScore = $recencyScore + $frequencyScore + $monetaryScore;

        // Update customer
        $customer->update([
            'rfm_recency_score' => $recencyScore,
            'rfm_frequency_score' => $frequencyScore,
            'rfm_monetary_score' => $monetaryScore,
            'rfm_segment' => $segment,
            'days_since_last_purchase' => $recency,
        ]);
    }

    /**
     * Determine segment based on RFM code.
     */
    protected function determineSegment(string $rfmCode): string
    {
        // Champions: Best customers - bought recently, buy often, spend the most
        $champions = ['555', '554', '544', '545', '454', '455', '445'];
        if (in_array($rfmCode, $champions)) {
            return 'Champions';
        }

        // Loyal Customers: Buy regularly
        $loyal = ['543', '444', '435', '355', '354', '345', '344', '335'];
        if (in_array($rfmCode, $loyal)) {
            return 'Loyal';
        }

        // Potential Loyalists: Recent customers with average frequency
        $potentialLoyalists = ['553', '551', '552', '541', '542', '533', '532', '531',
            '452', '451', '442', '441', '431', '453', '443', '433'];
        if (in_array($rfmCode, $potentialLoyalists)) {
            return 'Potential Loyalist';
        }

        // New Customers: Bought recently, but not often
        $newCustomers = ['512', '511', '422', '421', '412', '411', '311'];
        if (in_array($rfmCode, $newCustomers)) {
            return 'New Customers';
        }

        // Promising: Recent shoppers, but haven't spent much
        $promising = ['525', '524', '523', '522', '521', '515', '514', '513',
            '425', '424', '413', '414', '415', '315', '314', '313'];
        if (in_array($rfmCode, $promising)) {
            return 'Promising';
        }

        // Need Attention: Above average recency, frequency and monetary values
        $needAttention = ['535', '534', '443', '434', '343', '334', '325', '324'];
        if (in_array($rfmCode, $needAttention)) {
            return 'Need Attention';
        }

        // About To Sleep: Below average across the board
        $aboutToSleep = ['331', '321', '312', '221', '213', '231', '241', '251'];
        if (in_array($rfmCode, $aboutToSleep)) {
            return 'About To Sleep';
        }

        // At Risk: Spent big money, purchased often but long time ago
        $atRisk = ['255', '254', '245', '244', '253', '252', '243', '242',
            '235', '234', '225', '224', '153', '152', '145', '143', '142',
            '135', '134', '133', '125', '124'];
        if (in_array($rfmCode, $atRisk)) {
            return 'At Risk';
        }

        // Can't Lose Them: Made big purchases and often, but long time ago
        $cantLose = ['155', '154', '144', '214', '215', '115', '114', '113'];
        if (in_array($rfmCode, $cantLose)) {
            return 'Cannot Lose Them';
        }

        // Hibernating: Last purchase was long ago, low spenders
        $hibernating = ['332', '322', '233', '232', '223', '222', '132', '123',
            '122', '212', '211'];
        if (in_array($rfmCode, $hibernating)) {
            return 'Hibernating';
        }

        // Lost: Lowest recency, frequency, monetary scores
        $lost = ['111', '112', '121', '131', '141', '151'];
        if (in_array($rfmCode, $lost)) {
            return 'Lost';
        }

        return 'Other';
    }

    /**
     * Update days_since_last_purchase for all customers with purchases.
     */
    protected function updateDaysSinceLastPurchase(): void
    {
        DB::statement("
            UPDATE core_customers
            SET days_since_last_purchase = DATEDIFF(NOW(), last_purchase_at)
            WHERE last_purchase_at IS NOT NULL
        ");
    }

    /**
     * Update customer segments based on RFM and other factors.
     */
    protected function updateCustomerSegments(): void
    {
        // VIP: Champions with high spending
        DB::statement("
            UPDATE core_customers
            SET customer_segment = 'VIP'
            WHERE rfm_segment IN ('Champions', 'Loyal')
              AND total_spent >= 500
        ");

        // At Risk: Good customers who haven't purchased recently
        DB::statement("
            UPDATE core_customers
            SET customer_segment = 'At Risk'
            WHERE rfm_segment IN ('At Risk', 'Cannot Lose Them')
              AND customer_segment != 'VIP'
        ");

        // Lapsed VIP: Former VIPs who are now at risk
        DB::statement("
            UPDATE core_customers
            SET customer_segment = 'Lapsed VIP'
            WHERE total_spent >= 500
              AND days_since_last_purchase > 180
              AND customer_segment NOT IN ('VIP', 'At Risk')
        ");
    }
}
