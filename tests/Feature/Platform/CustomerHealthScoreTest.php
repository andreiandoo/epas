<?php

namespace Tests\Feature\Platform;

use App\Jobs\CalculateCustomerHealthScoresJob;
use App\Models\Platform\CoreCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerHealthScoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_calculates_health_score_for_champion_customer()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'champion-customer',
            'email_hash' => hash('sha256', 'champion@test.com'),
            'rfm_score' => 15, // Maximum RFM
            'engagement_score' => 80,
            'churn_risk_score' => 10, // Low risk
            'last_seen_at' => now()->subDays(1), // Very recent
            'first_seen_at' => now()->subMonths(6),
            'total_orders' => 10,
            'total_spent' => 5000,
        ]);

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $customer->refresh();

        $this->assertNotNull($customer->health_score);
        $this->assertGreaterThan(70, $customer->health_score); // Should be high
        $this->assertNotNull($customer->health_score_breakdown);
        $this->assertNotNull($customer->health_score_calculated_at);
    }

    /** @test */
    public function it_calculates_health_score_for_at_risk_customer()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'at-risk-customer',
            'email_hash' => hash('sha256', 'atrisk@test.com'),
            'rfm_score' => 5, // Low RFM
            'engagement_score' => 20,
            'churn_risk_score' => 80, // High risk
            'last_seen_at' => now()->subDays(60), // Not seen in a while
            'first_seen_at' => now()->subMonths(12),
            'total_orders' => 1,
            'total_spent' => 100,
        ]);

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $customer->refresh();

        $this->assertNotNull($customer->health_score);
        $this->assertLessThan(50, $customer->health_score); // Should be low
    }

    /** @test */
    public function it_assigns_correct_segment_based_on_score()
    {
        // Create customers with different profiles
        $champion = CoreCustomer::create([
            'uuid' => 'segment-champion',
            'email_hash' => hash('sha256', 'segment-champion@test.com'),
            'rfm_score' => 15,
            'engagement_score' => 90,
            'churn_risk_score' => 5,
            'last_seen_at' => now(),
            'first_seen_at' => now()->subMonths(6),
            'total_orders' => 15,
            'total_spent' => 3000,
        ]);

        $loyal = CoreCustomer::create([
            'uuid' => 'segment-loyal',
            'email_hash' => hash('sha256', 'segment-loyal@test.com'),
            'rfm_score' => 12,
            'engagement_score' => 70,
            'churn_risk_score' => 15,
            'last_seen_at' => now()->subDays(7),
            'first_seen_at' => now()->subMonths(8),
            'total_orders' => 8,
            'total_spent' => 1500,
        ]);

        $atRisk = CoreCustomer::create([
            'uuid' => 'segment-atrisk',
            'email_hash' => hash('sha256', 'segment-atrisk@test.com'),
            'rfm_score' => 6,
            'engagement_score' => 25,
            'churn_risk_score' => 70,
            'last_seen_at' => now()->subDays(45),
            'first_seen_at' => now()->subMonths(12),
            'total_orders' => 3,
            'total_spent' => 400,
        ]);

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $champion->refresh();
        $loyal->refresh();
        $atRisk->refresh();

        $this->assertEquals('champion', $champion->customer_segment);
        $this->assertContains($loyal->customer_segment, ['loyal', 'potential_loyalist']);
        $this->assertContains($atRisk->customer_segment, ['at_risk', 'hibernating', 'needs_attention']);
    }

    /** @test */
    public function it_stores_health_score_breakdown()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'breakdown-customer',
            'email_hash' => hash('sha256', 'breakdown@test.com'),
            'rfm_score' => 10,
            'engagement_score' => 50,
            'churn_risk_score' => 30,
            'last_seen_at' => now()->subDays(10),
            'first_seen_at' => now()->subMonths(3),
            'total_orders' => 5,
            'total_spent' => 800,
        ]);

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $customer->refresh();

        $breakdown = $customer->health_score_breakdown;

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('rfm_component', $breakdown);
        $this->assertArrayHasKey('engagement_component', $breakdown);
        $this->assertArrayHasKey('recency_component', $breakdown);
        $this->assertArrayHasKey('churn_component', $breakdown);
    }

    /** @test */
    public function it_processes_customers_in_batches()
    {
        // Create 100 customers
        for ($i = 0; $i < 100; $i++) {
            CoreCustomer::create([
                'uuid' => "batch-customer-{$i}",
                'email_hash' => hash('sha256', "batch{$i}@test.com"),
                'rfm_score' => rand(1, 15),
                'engagement_score' => rand(0, 100),
                'churn_risk_score' => rand(0, 100),
                'last_seen_at' => now()->subDays(rand(0, 90)),
                'first_seen_at' => now()->subMonths(rand(1, 12)),
                'total_orders' => rand(0, 20),
                'total_spent' => rand(0, 5000),
            ]);
        }

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $processedCount = CoreCustomer::whereNotNull('health_score_calculated_at')->count();

        $this->assertEquals(100, $processedCount);
    }

    /** @test */
    public function health_score_is_bounded_between_0_and_100()
    {
        // Create extreme case customers
        $extreme1 = CoreCustomer::create([
            'uuid' => 'extreme-high',
            'email_hash' => hash('sha256', 'high@test.com'),
            'rfm_score' => 20, // Above max
            'engagement_score' => 150, // Above max
            'churn_risk_score' => -10, // Below min
            'last_seen_at' => now(),
            'first_seen_at' => now()->subYears(2),
            'total_orders' => 100,
            'total_spent' => 50000,
        ]);

        $extreme2 = CoreCustomer::create([
            'uuid' => 'extreme-low',
            'email_hash' => hash('sha256', 'low@test.com'),
            'rfm_score' => -5, // Below min
            'engagement_score' => -10, // Below min
            'churn_risk_score' => 150, // Above max
            'last_seen_at' => now()->subYears(2),
            'first_seen_at' => now()->subYears(3),
            'total_orders' => 0,
            'total_spent' => 0,
        ]);

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $extreme1->refresh();
        $extreme2->refresh();

        $this->assertGreaterThanOrEqual(0, $extreme1->health_score);
        $this->assertLessThanOrEqual(100, $extreme1->health_score);
        $this->assertGreaterThanOrEqual(0, $extreme2->health_score);
        $this->assertLessThanOrEqual(100, $extreme2->health_score);
    }

    /** @test */
    public function it_handles_null_values_gracefully()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'null-values',
            'first_seen_at' => now(),
            // All other fields null
        ]);

        $job = new CalculateCustomerHealthScoresJob();
        $job->handle();

        $customer->refresh();

        $this->assertNotNull($customer->health_score);
        $this->assertGreaterThanOrEqual(0, $customer->health_score);
    }

    /** @test */
    public function get_health_score_label_returns_correct_label()
    {
        $excellent = CoreCustomer::create([
            'uuid' => 'label-excellent',
            'health_score' => 85,
            'first_seen_at' => now(),
        ]);

        $good = CoreCustomer::create([
            'uuid' => 'label-good',
            'health_score' => 65,
            'first_seen_at' => now(),
        ]);

        $fair = CoreCustomer::create([
            'uuid' => 'label-fair',
            'health_score' => 45,
            'first_seen_at' => now(),
        ]);

        $poor = CoreCustomer::create([
            'uuid' => 'label-poor',
            'health_score' => 25,
            'first_seen_at' => now(),
        ]);

        $this->assertEquals('Excellent', $excellent->getHealthScoreLabel());
        $this->assertEquals('Good', $good->getHealthScoreLabel());
        $this->assertEquals('Fair', $fair->getHealthScoreLabel());
        $this->assertEquals('Poor', $poor->getHealthScoreLabel());
    }
}
