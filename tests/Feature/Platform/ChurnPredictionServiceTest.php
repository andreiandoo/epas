<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Services\Platform\ChurnPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurnPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChurnPredictionService $churnService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->churnService = new ChurnPredictionService();
    }

    /** @test */
    public function it_predicts_churn_for_a_customer()
    {
        $customer = $this->createCustomer([
            'last_seen_at' => now()->subDays(60),
            'total_orders' => 5,
            'lifetime_value' => 500,
        ]);

        $prediction = $this->churnService->predictChurn($customer);

        $this->assertArrayHasKey('customer_id', $prediction);
        $this->assertArrayHasKey('churn_probability', $prediction);
        $this->assertArrayHasKey('risk_level', $prediction);
        $this->assertArrayHasKey('features', $prediction);
        $this->assertArrayHasKey('contributing_factors', $prediction);
        $this->assertArrayHasKey('recommendations', $prediction);
    }

    /** @test */
    public function it_returns_higher_risk_for_inactive_customers()
    {
        $activeCustomer = $this->createCustomer([
            'last_seen_at' => now()->subDays(5),
            'total_orders' => 10,
        ]);

        $inactiveCustomer = $this->createCustomer([
            'last_seen_at' => now()->subDays(100),
            'total_orders' => 10,
        ]);

        $activePrediction = $this->churnService->predictChurn($activeCustomer);
        $inactivePrediction = $this->churnService->predictChurn($inactiveCustomer);

        $this->assertGreaterThan(
            $activePrediction['churn_probability'],
            $inactivePrediction['churn_probability']
        );
    }

    /** @test */
    public function it_assigns_correct_risk_levels()
    {
        // Critical risk: 80%+
        $criticalCustomer = $this->createCustomer([
            'last_seen_at' => now()->subDays(120),
            'total_orders' => 1,
            'email_unsubscribed_at' => now()->subDays(30),
        ]);

        $prediction = $this->churnService->predictChurn($criticalCustomer);

        $this->assertContains($prediction['risk_level'], [
            ChurnPredictionService::RISK_CRITICAL,
            ChurnPredictionService::RISK_HIGH,
            ChurnPredictionService::RISK_MEDIUM,
        ]);
    }

    /** @test */
    public function it_provides_retention_recommendations()
    {
        $customer = $this->createCustomer([
            'last_seen_at' => now()->subDays(60),
            'total_orders' => 3,
        ]);

        $prediction = $this->churnService->predictChurn($customer);

        $this->assertIsArray($prediction['recommendations']);

        if (count($prediction['recommendations']) > 0) {
            $recommendation = $prediction['recommendations'][0];
            $this->assertArrayHasKey('action', $recommendation);
            $this->assertArrayHasKey('priority', $recommendation);
            $this->assertArrayHasKey('description', $recommendation);
        }
    }

    /** @test */
    public function it_gets_at_risk_customers()
    {
        // Create customers with various risk levels
        for ($i = 0; $i < 5; $i++) {
            $this->createCustomer([
                'last_seen_at' => now()->subDays($i * 30),
                'total_orders' => rand(1, 10),
                'total_spent' => rand(100, 1000),
            ]);
        }

        $atRiskCustomers = $this->churnService->getAtRiskCustomers(
            ChurnPredictionService::RISK_MEDIUM,
            50
        );

        $this->assertIsIterable($atRiskCustomers);

        foreach ($atRiskCustomers as $prediction) {
            $this->assertArrayHasKey('risk_level', $prediction);
            $this->assertContains($prediction['risk_level'], [
                ChurnPredictionService::RISK_CRITICAL,
                ChurnPredictionService::RISK_HIGH,
                ChurnPredictionService::RISK_MEDIUM,
            ]);
        }
    }

    /** @test */
    public function it_calculates_churn_stats_by_segment()
    {
        // Create customers in different segments
        $segments = ['champions', 'loyal_customers', 'at_risk'];
        foreach ($segments as $segment) {
            for ($i = 0; $i < 3; $i++) {
                $this->createCustomer([
                    'customer_segment' => $segment,
                    'total_orders' => rand(1, 10),
                    'total_spent' => rand(100, 1000),
                ]);
            }
        }

        $stats = $this->churnService->getChurnStatsBySegment();

        $this->assertIsArray($stats);

        foreach ($stats as $segment => $data) {
            $this->assertArrayHasKey('segment', $data);
            $this->assertArrayHasKey('customer_count', $data);
            $this->assertArrayHasKey('avg_churn_probability', $data);
            $this->assertArrayHasKey('risk_distribution', $data);
        }
    }

    /** @test */
    public function it_gets_cohort_churn_analysis()
    {
        // Create customers in different cohorts
        for ($i = 0; $i < 6; $i++) {
            $cohortMonth = now()->subMonths($i)->format('Y-m');
            $this->createCustomer([
                'cohort_month' => $cohortMonth,
                'total_orders' => rand(1, 5),
                'last_seen_at' => now()->subDays(rand(1, 60)),
            ]);
        }

        $analysis = $this->churnService->getCohortChurnAnalysis('month', 6);

        $this->assertIsArray($analysis);

        if (count($analysis) > 0) {
            $this->assertArrayHasKey('cohort', $analysis[0]);
            $this->assertArrayHasKey('total_customers', $analysis[0]);
            $this->assertArrayHasKey('actual_churn_rate', $analysis[0]);
        }
    }

    /** @test */
    public function it_returns_churn_dashboard_data()
    {
        // Create test customers
        for ($i = 0; $i < 10; $i++) {
            $this->createCustomer([
                'total_orders' => rand(1, 10),
                'total_spent' => rand(100, 1000),
                'churn_risk_score' => rand(0, 100),
            ]);
        }

        $dashboard = $this->churnService->getChurnDashboard();

        $this->assertArrayHasKey('summary', $dashboard);
        $this->assertArrayHasKey('risk_distribution', $dashboard);
        $this->assertArrayHasKey('total_customers', $dashboard['summary']);
        $this->assertArrayHasKey('at_risk_customers', $dashboard['summary']);
    }

    /** @test */
    public function it_updates_customer_churn_scores()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createCustomer([
                'total_orders' => rand(1, 10),
                'last_seen_at' => now()->subDays(rand(1, 90)),
            ]);
        }

        $result = $this->churnService->updateCustomerChurnScores(100);

        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertGreaterThan(0, $result['updated']);
    }

    /** @test */
    public function it_identifies_contributing_factors()
    {
        $customer = $this->createCustomer([
            'last_seen_at' => now()->subDays(90),
            'total_orders' => 2,
            'email_unsubscribed_at' => now()->subDays(10),
        ]);

        $prediction = $this->churnService->predictChurn($customer);

        $this->assertIsArray($prediction['contributing_factors']);

        // Should identify inactivity as a factor
        $factors = array_column($prediction['contributing_factors'], 'factor');
        $this->assertContains('Inactivity', $factors);
    }

    /** @test */
    public function it_provides_risk_labels()
    {
        $customer = $this->createCustomer([
            'last_seen_at' => now()->subDays(30),
            'total_orders' => 5,
        ]);

        $prediction = $this->churnService->predictChurn($customer);

        $this->assertArrayHasKey('risk_label', $prediction);
        $this->assertNotEmpty($prediction['risk_label']);
    }

    protected function createCustomer(array $attributes = []): CoreCustomer
    {
        $defaults = [
            'uuid' => 'test-' . uniqid(),
            'email_hash' => hash('sha256', uniqid() . '@test.com'),
            'total_spent' => 0,
            'total_orders' => 0,
            'first_seen_at' => now()->subDays(90),
            'last_seen_at' => now(),
        ];

        return CoreCustomer::create(array_merge($defaults, $attributes));
    }
}
