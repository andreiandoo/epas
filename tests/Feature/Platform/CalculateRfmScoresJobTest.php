<?php

namespace Tests\Feature\Platform;

use App\Jobs\CalculateRfmScoresJob;
use App\Models\Platform\CoreCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CalculateRfmScoresJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calculates_rfm_scores_for_customers_with_orders()
    {
        // Create a customer with purchase history
        $customer = CoreCustomer::create([
            'uuid' => 'test-uuid-001',
            'total_orders' => 5,
            'total_spent' => 500,
            'last_purchase_at' => now()->subDays(15),
            'first_seen_at' => now()->subMonths(3),
        ]);

        // Run the job
        $job = new CalculateRfmScoresJob();
        $job->handle();

        // Refresh and verify
        $customer->refresh();

        $this->assertNotNull($customer->rfm_recency_score);
        $this->assertNotNull($customer->rfm_frequency_score);
        $this->assertNotNull($customer->rfm_monetary_score);
        $this->assertNotNull($customer->rfm_segment);
    }

    /** @test */
    public function it_assigns_champions_segment_to_best_customers()
    {
        // Create a champion customer (recent, frequent, high spender)
        $customer = CoreCustomer::create([
            'uuid' => 'test-champion-001',
            'total_orders' => 15,
            'total_spent' => 2000,
            'last_purchase_at' => now()->subDays(5),
            'first_seen_at' => now()->subMonths(6),
        ]);

        $job = new CalculateRfmScoresJob();
        $job->handle();

        $customer->refresh();

        $this->assertEquals(5, $customer->rfm_recency_score);
        $this->assertEquals(5, $customer->rfm_frequency_score);
        $this->assertEquals(5, $customer->rfm_monetary_score);
        $this->assertEquals('Champions', $customer->rfm_segment);
    }

    /** @test */
    public function it_assigns_at_risk_segment_to_lapsed_customers()
    {
        // Create an at-risk customer (long time ago, was good customer)
        $customer = CoreCustomer::create([
            'uuid' => 'test-at-risk-001',
            'total_orders' => 8,
            'total_spent' => 800,
            'last_purchase_at' => now()->subDays(120),
            'first_seen_at' => now()->subMonths(12),
        ]);

        $job = new CalculateRfmScoresJob();
        $job->handle();

        $customer->refresh();

        // Recency should be low (1-2) as they haven't purchased recently
        $this->assertLessThanOrEqual(2, $customer->rfm_recency_score);
        // Frequency should be high (4-5)
        $this->assertGreaterThanOrEqual(4, $customer->rfm_frequency_score);
        // Monetary should be high (4-5)
        $this->assertGreaterThanOrEqual(4, $customer->rfm_monetary_score);
    }

    /** @test */
    public function it_assigns_new_customer_segment_correctly()
    {
        // Create a new customer (recent, single purchase, low spend)
        $customer = CoreCustomer::create([
            'uuid' => 'test-new-001',
            'total_orders' => 1,
            'total_spent' => 50,
            'last_purchase_at' => now()->subDays(3),
            'first_seen_at' => now()->subDays(5),
        ]);

        $job = new CalculateRfmScoresJob();
        $job->handle();

        $customer->refresh();

        // Recency should be high (recent purchase)
        $this->assertGreaterThanOrEqual(4, $customer->rfm_recency_score);
        // Frequency should be low (only 1 order)
        $this->assertEquals(1, $customer->rfm_frequency_score);
        // Monetary should be low
        $this->assertLessThanOrEqual(2, $customer->rfm_monetary_score);
    }

    /** @test */
    public function it_skips_customers_without_orders()
    {
        // Create a customer without any orders
        $customer = CoreCustomer::create([
            'uuid' => 'test-no-orders-001',
            'total_orders' => 0,
            'total_spent' => 0,
            'first_seen_at' => now()->subDays(30),
        ]);

        $job = new CalculateRfmScoresJob();
        $job->handle();

        $customer->refresh();

        // RFM scores should remain null for non-purchasers
        $this->assertNull($customer->rfm_recency_score);
        $this->assertNull($customer->rfm_frequency_score);
        $this->assertNull($customer->rfm_monetary_score);
    }

    /** @test */
    public function it_updates_days_since_last_purchase()
    {
        $purchaseDate = now()->subDays(45);

        $customer = CoreCustomer::create([
            'uuid' => 'test-days-001',
            'total_orders' => 2,
            'total_spent' => 100,
            'last_purchase_at' => $purchaseDate,
            'first_seen_at' => now()->subMonths(2),
        ]);

        $job = new CalculateRfmScoresJob();
        $job->handle();

        $customer->refresh();

        // Days should be approximately 45 (might be 44 or 46 depending on timing)
        $this->assertGreaterThanOrEqual(44, $customer->days_since_last_purchase);
        $this->assertLessThanOrEqual(46, $customer->days_since_last_purchase);
    }

    /** @test */
    public function it_processes_multiple_customers_in_batches()
    {
        // Create multiple customers
        for ($i = 1; $i <= 10; $i++) {
            CoreCustomer::create([
                'uuid' => "test-batch-{$i}",
                'total_orders' => $i,
                'total_spent' => $i * 100,
                'last_purchase_at' => now()->subDays($i * 10),
                'first_seen_at' => now()->subMonths(6),
            ]);
        }

        $job = new CalculateRfmScoresJob();
        $job->handle();

        // All customers should have RFM scores
        $customersWithScores = CoreCustomer::whereNotNull('rfm_segment')->count();
        $this->assertEquals(10, $customersWithScores);
    }

    /** @test */
    public function it_handles_customer_calculation_errors_gracefully()
    {
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->never();

        $customer = CoreCustomer::create([
            'uuid' => 'test-error-001',
            'total_orders' => 3,
            'total_spent' => 150,
            'last_purchase_at' => now()->subDays(20),
            'first_seen_at' => now()->subMonths(2),
        ]);

        $job = new CalculateRfmScoresJob();

        // Should not throw an exception
        $job->handle();

        $customer->refresh();
        $this->assertNotNull($customer->rfm_segment);
    }

    /** @test */
    public function it_updates_customer_segments_based_on_rfm()
    {
        // Create VIP customer
        $vipCustomer = CoreCustomer::create([
            'uuid' => 'test-vip-001',
            'total_orders' => 10,
            'total_spent' => 1500,
            'last_purchase_at' => now()->subDays(10),
            'first_seen_at' => now()->subMonths(12),
        ]);

        // Create at-risk customer
        $atRiskCustomer = CoreCustomer::create([
            'uuid' => 'test-atrisk-001',
            'total_orders' => 5,
            'total_spent' => 400,
            'last_purchase_at' => now()->subDays(100),
            'first_seen_at' => now()->subMonths(8),
        ]);

        $job = new CalculateRfmScoresJob();
        $job->handle();

        $vipCustomer->refresh();
        $atRiskCustomer->refresh();

        // VIP should be marked as VIP
        $this->assertEquals('VIP', $vipCustomer->customer_segment);
    }
}
