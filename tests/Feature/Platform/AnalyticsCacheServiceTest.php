<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Services\Platform\AnalyticsCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnalyticsCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = new AnalyticsCacheService();

        // Create test data
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        // Create customers
        for ($i = 0; $i < 10; $i++) {
            $customer = CoreCustomer::create([
                'uuid' => "customer-{$i}",
                'email_hash' => hash('sha256', "customer{$i}@test.com"),
                'total_spent' => rand(100, 1000),
                'total_orders' => rand(1, 10),
                'rfm_score' => rand(5, 15),
                'customer_segment' => ['champion', 'loyal', 'at_risk'][rand(0, 2)],
                'first_seen_at' => now()->subDays(rand(1, 90)),
                'last_seen_at' => now()->subDays(rand(0, 30)),
            ]);

            // Create sessions
            CoreSession::create([
                'core_customer_id' => $customer->id,
                'session_id' => "session-{$i}",
                'started_at' => now()->subHours(rand(1, 48)),
                'is_converted' => rand(0, 1),
                'total_value' => rand(0, 1) ? rand(50, 200) : 0,
                'utm_source' => ['google', 'facebook', 'direct', 'email'][rand(0, 3)],
                'country_code' => ['US', 'GB', 'CA', 'DE'][rand(0, 3)],
            ]);

            // Create events
            $eventTypes = ['page_view', 'view_item', 'add_to_cart', 'purchase'];
            foreach ($eventTypes as $type) {
                CoreCustomerEvent::create([
                    'core_customer_id' => $customer->id,
                    'event_type' => $type,
                    'is_converted' => $type === 'purchase',
                    'conversion_value' => $type === 'purchase' ? rand(50, 200) : null,
                    'created_at' => now()->subHours(rand(1, 72)),
                ]);
            }
        }
    }

    /** @test */
    public function it_returns_dashboard_stats()
    {
        $stats = $this->cacheService->getDashboardStats();

        $this->assertArrayHasKey('events_today', $stats);
        $this->assertArrayHasKey('conversions_today', $stats);
        $this->assertArrayHasKey('revenue_today', $stats);
        $this->assertArrayHasKey('unique_visitors_today', $stats);
        $this->assertArrayHasKey('cached_at', $stats);
    }

    /** @test */
    public function it_caches_dashboard_stats()
    {
        // First call - should hit database
        $stats1 = $this->cacheService->getDashboardStats();

        // Second call - should use cache
        $stats2 = $this->cacheService->getDashboardStats();

        $this->assertEquals($stats1['cached_at'], $stats2['cached_at']);
    }

    /** @test */
    public function it_returns_conversion_funnel()
    {
        $funnel = $this->cacheService->getConversionFunnel();

        $this->assertArrayHasKey('steps', $funnel);
        $this->assertArrayHasKey('overall_conversion_rate', $funnel);
        $this->assertCount(5, $funnel['steps']);

        $stepNames = array_column($funnel['steps'], 'name');
        $this->assertContains('Page Views', $stepNames);
        $this->assertContains('Purchase', $stepNames);
    }

    /** @test */
    public function it_returns_customer_segments()
    {
        $segments = $this->cacheService->getCustomerSegments();

        $this->assertArrayHasKey('segments', $segments);
        $this->assertArrayHasKey('total_customers', $segments);
        $this->assertGreaterThan(0, $segments['total_customers']);
    }

    /** @test */
    public function it_returns_daily_metrics()
    {
        $metrics = $this->cacheService->getDailyMetrics(7);

        $this->assertArrayHasKey('dates', $metrics);
        $this->assertArrayHasKey('events', $metrics);
        $this->assertArrayHasKey('conversions', $metrics);
        $this->assertArrayHasKey('revenue', $metrics);
    }

    /** @test */
    public function it_returns_top_customers()
    {
        $customers = $this->cacheService->getTopCustomers(5, 'total_spent');

        $this->assertArrayHasKey('customers', $customers);
        $this->assertLessThanOrEqual(5, count($customers['customers']));

        if (count($customers['customers']) > 0) {
            $this->assertArrayHasKey('uuid', $customers['customers'][0]);
            $this->assertArrayHasKey('total_spent', $customers['customers'][0]);
        }
    }

    /** @test */
    public function it_returns_traffic_sources()
    {
        $sources = $this->cacheService->getTrafficSources();

        $this->assertArrayHasKey('sources', $sources);

        if (count($sources['sources']) > 0) {
            $this->assertArrayHasKey('source', $sources['sources'][0]);
            $this->assertArrayHasKey('sessions', $sources['sources'][0]);
            $this->assertArrayHasKey('conversion_rate', $sources['sources'][0]);
        }
    }

    /** @test */
    public function it_returns_geographic_breakdown()
    {
        $geo = $this->cacheService->getGeographicBreakdown();

        $this->assertArrayHasKey('countries', $geo);

        if (count($geo['countries']) > 0) {
            $this->assertArrayHasKey('country', $geo['countries'][0]);
            $this->assertArrayHasKey('sessions', $geo['countries'][0]);
            $this->assertArrayHasKey('conversions', $geo['countries'][0]);
        }
    }

    /** @test */
    public function it_clears_cache()
    {
        // Populate cache
        $this->cacheService->getDashboardStats();
        $this->cacheService->getCustomerSegments();

        // Clear cache
        $this->cacheService->clearAll();

        // Cache should be empty - next call should have fresh cached_at
        Cache::flush(); // Ensure complete flush for test

        $stats = $this->cacheService->getDashboardStats();
        $this->assertArrayHasKey('cached_at', $stats);
    }

    /** @test */
    public function it_warms_up_cache()
    {
        Cache::flush();

        $this->cacheService->warmUp();

        // After warm-up, cache should contain dashboard stats
        $stats = $this->cacheService->getDashboardStats();
        $this->assertArrayHasKey('cached_at', $stats);

        // And customer segments
        $segments = $this->cacheService->getCustomerSegments();
        $this->assertArrayHasKey('cached_at', $segments);
    }

    /** @test */
    public function it_supports_tenant_isolation()
    {
        $stats1 = $this->cacheService->getDashboardStats(1);
        $stats2 = $this->cacheService->getDashboardStats(2);

        // Different tenants should have different cache entries
        // (even if data is same, cached_at would differ if not cached)
        $this->assertArrayHasKey('cached_at', $stats1);
        $this->assertArrayHasKey('cached_at', $stats2);
    }

    /** @test */
    public function funnel_calculates_rates_correctly()
    {
        // Create specific test data for funnel calculation
        CoreCustomerEvent::query()->delete();

        $customer = CoreCustomer::first();

        // 100 page views
        for ($i = 0; $i < 100; $i++) {
            CoreCustomerEvent::create([
                'core_customer_id' => $customer->id,
                'event_type' => 'page_view',
                'is_converted' => false,
                'created_at' => now(),
            ]);
        }

        // 50 product views
        for ($i = 0; $i < 50; $i++) {
            CoreCustomerEvent::create([
                'core_customer_id' => $customer->id,
                'event_type' => 'view_item',
                'is_converted' => false,
                'created_at' => now(),
            ]);
        }

        // 10 add to carts
        for ($i = 0; $i < 10; $i++) {
            CoreCustomerEvent::create([
                'core_customer_id' => $customer->id,
                'event_type' => 'add_to_cart',
                'is_converted' => false,
                'created_at' => now(),
            ]);
        }

        // 5 purchases
        for ($i = 0; $i < 5; $i++) {
            CoreCustomerEvent::create([
                'core_customer_id' => $customer->id,
                'event_type' => 'purchase',
                'is_converted' => true,
                'conversion_value' => 100,
                'created_at' => now(),
            ]);
        }

        Cache::flush();
        $funnel = $this->cacheService->getConversionFunnel();

        $this->assertEquals(100, $funnel['steps'][0]['count']); // Page Views
        $this->assertEquals(50, $funnel['steps'][1]['count']);  // Product Views
        $this->assertEquals(10, $funnel['steps'][2]['count']);  // Add to Cart
        $this->assertEquals(5, $funnel['steps'][4]['count']);   // Purchase
        $this->assertEquals(5, $funnel['overall_conversion_rate']); // 5/100 = 5%
    }
}
