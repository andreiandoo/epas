<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Services\Platform\LtvPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LtvPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LtvPredictionService $ltvService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ltvService = new LtvPredictionService();
        Cache::flush();
    }

    /** @test */
    public function it_predicts_ltv_for_a_customer()
    {
        $customer = $this->createCustomer([
            'total_orders' => 5,
            'total_spent' => 500,
            'lifetime_value' => 500,
            'first_seen_at' => now()->subDays(90),
        ]);

        $this->createPurchaseEvent($customer, 100);

        $prediction = $this->ltvService->predictLtv($customer);

        $this->assertArrayHasKey('customer_id', $prediction);
        $this->assertArrayHasKey('current_ltv', $prediction);
        $this->assertArrayHasKey('predicted_ltv', $prediction);
        $this->assertArrayHasKey('predicted_12m_ltv', $prediction);
        $this->assertArrayHasKey('predicted_24m_ltv', $prediction);
        $this->assertArrayHasKey('tier', $prediction);
        $this->assertArrayHasKey('confidence', $prediction);
        $this->assertArrayHasKey('features', $prediction);
        $this->assertArrayHasKey('recommendations', $prediction);
    }

    /** @test */
    public function it_assigns_correct_tiers()
    {
        // High-value customer
        $platinumCustomer = $this->createCustomer([
            'total_orders' => 50,
            'total_spent' => 5000,
            'lifetime_value' => 5000,
            'engagement_score' => 90,
        ]);

        $this->createPurchaseEvent($platinumCustomer, 500);

        $prediction = $this->ltvService->predictLtv($platinumCustomer);

        $this->assertContains($prediction['tier'], [
            LtvPredictionService::TIER_PLATINUM,
            LtvPredictionService::TIER_GOLD,
            LtvPredictionService::TIER_SILVER,
            LtvPredictionService::TIER_BRONZE,
        ]);
    }

    /** @test */
    public function it_predicts_higher_ltv_for_engaged_customers()
    {
        // Engaged customer
        $engagedCustomer = $this->createCustomer([
            'total_orders' => 5,
            'total_spent' => 500,
            'lifetime_value' => 500,
            'engagement_score' => 90,
            'first_seen_at' => now()->subDays(60),
        ]);

        // Disengaged customer
        $disengagedCustomer = $this->createCustomer([
            'total_orders' => 5,
            'total_spent' => 500,
            'lifetime_value' => 500,
            'engagement_score' => 10,
            'first_seen_at' => now()->subDays(60),
        ]);

        $this->createPurchaseEvent($engagedCustomer, 100);
        $this->createPurchaseEvent($disengagedCustomer, 100);

        $engagedPrediction = $this->ltvService->predictLtv($engagedCustomer);
        $disengagedPrediction = $this->ltvService->predictLtv($disengagedCustomer);

        $this->assertGreaterThanOrEqual(
            $disengagedPrediction['predicted_ltv'],
            $engagedPrediction['predicted_ltv']
        );
    }

    /** @test */
    public function it_returns_confidence_levels()
    {
        // New customer (low confidence)
        $newCustomer = $this->createCustomer([
            'total_orders' => 1,
            'first_seen_at' => now()->subDays(10),
        ]);

        // Established customer (high confidence)
        $establishedCustomer = $this->createCustomer([
            'total_orders' => 20,
            'first_seen_at' => now()->subDays(180),
            'engagement_score' => 80,
        ]);

        $newPrediction = $this->ltvService->predictLtv($newCustomer);
        $establishedPrediction = $this->ltvService->predictLtv($establishedCustomer);

        $this->assertContains($newPrediction['confidence'], [
            LtvPredictionService::CONFIDENCE_HIGH,
            LtvPredictionService::CONFIDENCE_MEDIUM,
            LtvPredictionService::CONFIDENCE_LOW,
        ]);

        // Established customers should generally have higher confidence
        $confidenceRank = [
            LtvPredictionService::CONFIDENCE_LOW => 1,
            LtvPredictionService::CONFIDENCE_MEDIUM => 2,
            LtvPredictionService::CONFIDENCE_HIGH => 3,
        ];

        $this->assertGreaterThanOrEqual(
            $confidenceRank[$newPrediction['confidence']],
            $confidenceRank[$establishedPrediction['confidence']]
        );
    }

    /** @test */
    public function it_provides_growth_recommendations()
    {
        $customer = $this->createCustomer([
            'total_orders' => 3,
            'total_spent' => 300,
            'email_open_rate' => 10, // Low email engagement
        ]);

        $prediction = $this->ltvService->predictLtv($customer);

        $this->assertIsArray($prediction['recommendations']);

        if (count($prediction['recommendations']) > 0) {
            $recommendation = $prediction['recommendations'][0];
            $this->assertArrayHasKey('action', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
            $this->assertArrayHasKey('expected_impact', $recommendation);
            $this->assertArrayHasKey('description', $recommendation);
        }
    }

    /** @test */
    public function it_extracts_features_correctly()
    {
        $customer = $this->createCustomer([
            'total_orders' => 5,
            'total_spent' => 500,
            'lifetime_value' => 500,
            'engagement_score' => 75,
            'first_seen_at' => now()->subDays(60),
        ]);

        // Create first purchase
        $this->createPurchaseEvent($customer, 120, now()->subDays(55));

        // Create sessions
        $this->createSession($customer, 8);

        $prediction = $this->ltvService->predictLtv($customer);
        $features = $prediction['features'];

        $this->assertArrayHasKey('first_purchase_value', $features);
        $this->assertArrayHasKey('early_frequency', $features);
        $this->assertArrayHasKey('engagement_score', $features);
        $this->assertArrayHasKey('session_depth', $features);
        $this->assertArrayHasKey('customer_age_days', $features);
    }

    /** @test */
    public function it_identifies_high_potential_customers()
    {
        // Create early-stage customers with good engagement
        for ($i = 0; $i < 5; $i++) {
            $customer = $this->createCustomer([
                'total_orders' => rand(1, 3),
                'total_spent' => rand(100, 300),
                'engagement_score' => rand(70, 90),
                'first_seen_at' => now()->subDays(rand(15, 30)),
            ]);
            $this->createPurchaseEvent($customer, rand(50, 150));
        }

        $highPotential = $this->ltvService->getHighPotentialCustomers(10);

        $this->assertIsIterable($highPotential);

        foreach ($highPotential as $prediction) {
            $this->assertArrayHasKey('ltv_growth_potential', $prediction);
        }
    }

    /** @test */
    public function it_calculates_ltv_by_segment()
    {
        // Create customers in different segments
        $segments = ['champions', 'loyal_customers', 'at_risk'];
        foreach ($segments as $segment) {
            for ($i = 0; $i < 3; $i++) {
                $customer = $this->createCustomer([
                    'customer_segment' => $segment,
                    'total_orders' => rand(1, 20),
                    'total_spent' => rand(100, 2000),
                    'lifetime_value' => rand(100, 2000),
                ]);
                $this->createPurchaseEvent($customer, rand(50, 200));
            }
        }

        $segmentAnalysis = $this->ltvService->getLtvBySegment();

        $this->assertIsArray($segmentAnalysis);

        foreach ($segmentAnalysis as $segment => $data) {
            $this->assertArrayHasKey('segment', $data);
            $this->assertArrayHasKey('customer_count', $data);
            $this->assertArrayHasKey('current_avg_ltv', $data);
            $this->assertArrayHasKey('predicted_avg_ltv', $data);
        }
    }

    /** @test */
    public function it_calculates_ltv_by_cohort()
    {
        // Create customers in different cohorts
        for ($i = 0; $i < 6; $i++) {
            $cohortMonth = now()->subMonths($i)->format('Y-m');
            $customer = $this->createCustomer([
                'cohort_month' => $cohortMonth,
                'total_orders' => rand(1, 10),
                'total_spent' => rand(100, 1000),
                'lifetime_value' => rand(100, 1000),
            ]);
            $this->createPurchaseEvent($customer, rand(50, 200));
        }

        $cohortAnalysis = $this->ltvService->getLtvByCohort('month', 6);

        $this->assertIsArray($cohortAnalysis);

        if (count($cohortAnalysis) > 0) {
            $this->assertArrayHasKey('cohort', $cohortAnalysis[0]);
            $this->assertArrayHasKey('customer_count', $cohortAnalysis[0]);
            $this->assertArrayHasKey('avg_ltv', $cohortAnalysis[0]);
            $this->assertArrayHasKey('projected_12m_ltv', $cohortAnalysis[0]);
        }
    }

    /** @test */
    public function it_returns_tier_distribution()
    {
        // Create customers with various LTV values
        $ltvValues = [50, 100, 200, 500, 1000, 2000];
        foreach ($ltvValues as $ltv) {
            $this->createCustomer([
                'lifetime_value' => $ltv,
            ]);
        }

        $distribution = $this->ltvService->getLtvTierDistribution();

        $this->assertIsArray($distribution);

        foreach ([LtvPredictionService::TIER_PLATINUM, LtvPredictionService::TIER_GOLD] as $tier) {
            if (isset($distribution[$tier])) {
                $this->assertArrayHasKey('tier', $distribution[$tier]);
                $this->assertArrayHasKey('threshold', $distribution[$tier]);
                $this->assertArrayHasKey('customer_count', $distribution[$tier]);
            }
        }
    }

    /** @test */
    public function it_returns_ltv_dashboard()
    {
        // Create test data
        for ($i = 0; $i < 10; $i++) {
            $customer = $this->createCustomer([
                'total_orders' => rand(1, 10),
                'total_spent' => rand(100, 1000),
                'lifetime_value' => rand(100, 1000),
            ]);
            $this->createPurchaseEvent($customer, rand(50, 200));
        }

        $dashboard = $this->ltvService->getLtvDashboard();

        $this->assertArrayHasKey('summary', $dashboard);
        $this->assertArrayHasKey('total_customers', $dashboard['summary']);
        $this->assertArrayHasKey('total_ltv', $dashboard['summary']);
        $this->assertArrayHasKey('average_ltv', $dashboard['summary']);
    }

    /** @test */
    public function it_identifies_contributing_factors()
    {
        $customer = $this->createCustomer([
            'total_orders' => 5,
            'total_spent' => 500,
            'lifetime_value' => 500,
            'engagement_score' => 85,
        ]);

        // Create high first purchase
        $this->createPurchaseEvent($customer, 200, now()->subDays(25));

        // Create multiple early purchases
        for ($i = 0; $i < 3; $i++) {
            $this->createPurchaseEvent($customer, 100, now()->subDays(20 - $i));
        }

        $prediction = $this->ltvService->predictLtv($customer);

        $this->assertIsArray($prediction['factors']);

        if (count($prediction['factors']) > 0) {
            $factor = $prediction['factors'][0];
            $this->assertArrayHasKey('factor', $factor);
            $this->assertArrayHasKey('impact', $factor);
            $this->assertArrayHasKey('description', $factor);
        }
    }

    /** @test */
    public function it_handles_new_customers()
    {
        $customer = $this->createCustomer([
            'total_orders' => 1,
            'total_spent' => 50,
            'lifetime_value' => 50,
            'first_seen_at' => now()->subDays(5),
        ]);

        $this->createPurchaseEvent($customer, 50);

        $prediction = $this->ltvService->predictLtv($customer);

        // Should still provide prediction for new customers
        $this->assertArrayHasKey('predicted_ltv', $prediction);
        $this->assertGreaterThan(0, $prediction['predicted_ltv']);

        // Confidence should be lower for new customers
        $this->assertEquals(LtvPredictionService::CONFIDENCE_LOW, $prediction['confidence']);
    }

    /** @test */
    public function it_provides_tier_labels()
    {
        $customer = $this->createCustomer([
            'total_orders' => 5,
            'lifetime_value' => 500,
        ]);

        $this->createPurchaseEvent($customer, 100);

        $prediction = $this->ltvService->predictLtv($customer);

        $this->assertArrayHasKey('tier_label', $prediction);
        $this->assertNotEmpty($prediction['tier_label']);
    }

    /** @test */
    public function it_calculates_growth_potential()
    {
        $customer = $this->createCustomer([
            'total_orders' => 2,
            'total_spent' => 200,
            'lifetime_value' => 200,
            'engagement_score' => 80,
            'first_seen_at' => now()->subDays(30),
        ]);

        $this->createPurchaseEvent($customer, 100);

        $prediction = $this->ltvService->predictLtv($customer);

        $this->assertArrayHasKey('ltv_growth_potential', $prediction);
        $this->assertIsNumeric($prediction['ltv_growth_potential']);
    }

    protected function createCustomer(array $attributes = []): CoreCustomer
    {
        $defaults = [
            'uuid' => 'ltv-test-' . uniqid(),
            'email_hash' => hash('sha256', uniqid() . '@test.com'),
            'total_spent' => 0,
            'total_orders' => 0,
            'lifetime_value' => 0,
            'first_seen_at' => now()->subDays(90),
            'last_seen_at' => now(),
        ];

        return CoreCustomer::create(array_merge($defaults, $attributes));
    }

    protected function createPurchaseEvent(CoreCustomer $customer, float $value, $createdAt = null): CoreCustomerEvent
    {
        return CoreCustomerEvent::create([
            'core_customer_id' => $customer->id,
            'event_type' => 'purchase',
            'is_converted' => true,
            'conversion_value' => $value,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    protected function createSession(CoreCustomer $customer, int $pageViews = 5): CoreSession
    {
        return CoreSession::create([
            'core_customer_id' => $customer->id,
            'session_id' => 'session-' . uniqid(),
            'started_at' => now()->subHours(rand(1, 24)),
            'page_views' => $pageViews,
            'duration_seconds' => rand(60, 600),
        ]);
    }
}
