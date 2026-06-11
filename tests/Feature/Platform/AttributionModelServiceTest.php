<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Services\Platform\AttributionModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AttributionModelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AttributionModelService $attributionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributionService = new AttributionModelService();
    }

    /** @test */
    public function it_calculates_first_touch_attribution()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'google', 'value' => 0],
            ['channel' => 'facebook', 'value' => 0],
            ['channel' => 'email', 'value' => 100],
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'first_touch'
        );

        $this->assertArrayHasKey('channels', $attribution);
        $this->assertEquals(100, $attribution['channels']['google']['attributed_value'] ?? 0);
        $this->assertEquals(0, $attribution['channels']['facebook']['attributed_value'] ?? 0);
    }

    /** @test */
    public function it_calculates_last_touch_attribution()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'google', 'value' => 0],
            ['channel' => 'facebook', 'value' => 0],
            ['channel' => 'email', 'value' => 100],
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'last_touch'
        );

        $this->assertArrayHasKey('channels', $attribution);
        $this->assertEquals(100, $attribution['channels']['email']['attributed_value'] ?? 0);
    }

    /** @test */
    public function it_calculates_linear_attribution()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'google', 'value' => 0],
            ['channel' => 'facebook', 'value' => 0],
            ['channel' => 'email', 'value' => 100],
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            90, // Value divisible by 3 for clean test
            'linear'
        );

        $this->assertArrayHasKey('channels', $attribution);

        // Each channel should get equal credit
        $googleValue = $attribution['channels']['google']['attributed_value'] ?? 0;
        $facebookValue = $attribution['channels']['facebook']['attributed_value'] ?? 0;
        $emailValue = $attribution['channels']['email']['attributed_value'] ?? 0;

        $this->assertEquals($googleValue, $facebookValue);
        $this->assertEquals($facebookValue, $emailValue);
        $this->assertEquals(90, $googleValue + $facebookValue + $emailValue);
    }

    /** @test */
    public function it_calculates_time_decay_attribution()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'google', 'value' => 0, 'days_ago' => 14],
            ['channel' => 'facebook', 'value' => 0, 'days_ago' => 7],
            ['channel' => 'email', 'value' => 100, 'days_ago' => 1],
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'time_decay'
        );

        $this->assertArrayHasKey('channels', $attribution);

        // More recent touches should get more credit
        $googleValue = $attribution['channels']['google']['attributed_value'] ?? 0;
        $emailValue = $attribution['channels']['email']['attributed_value'] ?? 0;

        $this->assertGreaterThan($googleValue, $emailValue);
    }

    /** @test */
    public function it_calculates_position_based_attribution()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'google', 'value' => 0],
            ['channel' => 'facebook', 'value' => 0],
            ['channel' => 'instagram', 'value' => 0],
            ['channel' => 'email', 'value' => 100],
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'position_based'
        );

        $this->assertArrayHasKey('channels', $attribution);

        // First and last should get 40% each (40 each), middle shares 20% (10 each)
        $googleValue = $attribution['channels']['google']['attributed_value'] ?? 0;
        $emailValue = $attribution['channels']['email']['attributed_value'] ?? 0;
        $facebookValue = $attribution['channels']['facebook']['attributed_value'] ?? 0;

        // First and last touch get more credit
        $this->assertGreaterThan($facebookValue, $googleValue);
        $this->assertGreaterThan($facebookValue, $emailValue);
    }

    /** @test */
    public function it_returns_available_models()
    {
        $models = AttributionModelService::getAvailableModels();

        $this->assertIsArray($models);
        $this->assertArrayHasKey('first_touch', $models);
        $this->assertArrayHasKey('last_touch', $models);
        $this->assertArrayHasKey('linear', $models);
        $this->assertArrayHasKey('time_decay', $models);
        $this->assertArrayHasKey('position_based', $models);
    }

    /** @test */
    public function it_compares_multiple_attribution_models()
    {
        // Create customers with journeys and conversions
        for ($i = 0; $i < 5; $i++) {
            $this->createCustomerWithJourney([
                ['channel' => 'google', 'value' => 0],
                ['channel' => 'facebook', 'value' => 0],
                ['channel' => 'email', 'value' => rand(50, 200)],
            ]);
        }

        $comparison = $this->attributionService->compareModels(
            Carbon::now()->subDays(30),
            Carbon::now()
        );

        $this->assertArrayHasKey('models', $comparison);

        foreach (['first_touch', 'last_touch', 'linear'] as $model) {
            $this->assertArrayHasKey($model, $comparison['models']);
            $this->assertArrayHasKey('total_attributed', $comparison['models'][$model]);
            $this->assertArrayHasKey('channels', $comparison['models'][$model]);
        }
    }

    /** @test */
    public function it_generates_channel_attribution_report()
    {
        // Create test data
        for ($i = 0; $i < 3; $i++) {
            $this->createCustomerWithJourney([
                ['channel' => 'google', 'value' => rand(50, 100)],
            ]);
        }

        $report = $this->attributionService->getChannelAttributionReport(
            Carbon::now()->subDays(30),
            Carbon::now()
        );

        $this->assertArrayHasKey('channels', $report);
        $this->assertArrayHasKey('date_range', $report);
        $this->assertArrayHasKey('total_conversions', $report);
    }

    /** @test */
    public function it_analyzes_customer_journey()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'google', 'value' => 0],
            ['channel' => 'facebook', 'value' => 0],
            ['channel' => 'email', 'value' => 100],
        ]);

        $analysis = $this->attributionService->analyzeCustomerJourney($customer->id);

        $this->assertArrayHasKey('customer_id', $analysis);
        $this->assertArrayHasKey('total_touchpoints', $analysis);
        $this->assertArrayHasKey('total_conversions', $analysis);
        $this->assertArrayHasKey('journey_path', $analysis);
        $this->assertArrayHasKey('channel_distribution', $analysis);
    }

    /** @test */
    public function it_handles_single_touchpoint_journey()
    {
        $customer = $this->createCustomerWithJourney([
            ['channel' => 'direct', 'value' => 100],
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'position_based'
        );

        // Single touchpoint should get 100% credit
        $this->assertEquals(100, $attribution['channels']['direct']['attributed_value'] ?? 0);
    }

    /** @test */
    public function it_handles_customer_with_no_touchpoints()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'no-journey-' . uniqid(),
            'email_hash' => hash('sha256', 'nojourney@test.com'),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'linear'
        );

        $this->assertArrayHasKey('channels', $attribution);
        $this->assertEmpty($attribution['channels']);
    }

    /** @test */
    public function it_respects_attribution_window()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'window-test-' . uniqid(),
            'email_hash' => hash('sha256', 'window@test.com'),
            'first_seen_at' => now()->subDays(60),
            'last_seen_at' => now(),
        ]);

        // Create event outside attribution window (35 days ago)
        CoreCustomerEvent::create([
            'core_customer_id' => $customer->id,
            'event_type' => 'page_view',
            'source' => 'google',
            'created_at' => now()->subDays(35),
        ]);

        // Create event within window (10 days ago)
        CoreCustomerEvent::create([
            'core_customer_id' => $customer->id,
            'event_type' => 'page_view',
            'source' => 'facebook',
            'created_at' => now()->subDays(10),
        ]);

        // Purchase
        CoreCustomerEvent::create([
            'core_customer_id' => $customer->id,
            'event_type' => 'purchase',
            'source' => 'facebook',
            'is_converted' => true,
            'conversion_value' => 100,
            'created_at' => now(),
        ]);

        // Set 30-day window (default)
        $this->attributionService->setAttributionWindow(30);

        $attribution = $this->attributionService->calculateAttributionForConversion(
            $customer,
            100,
            'first_touch'
        );

        // Google event should be excluded (outside window)
        // Facebook should get credit as it's the first within window
        $facebookValue = $attribution['channels']['facebook']['attributed_value'] ?? 0;
        $this->assertGreaterThan(0, $facebookValue);
    }

    /** @test */
    public function it_returns_error_for_invalid_customer()
    {
        $analysis = $this->attributionService->analyzeCustomerJourney(99999);

        $this->assertArrayHasKey('error', $analysis);
    }

    protected function createCustomerWithJourney(array $touchpoints): CoreCustomer
    {
        $customer = CoreCustomer::create([
            'uuid' => 'journey-' . uniqid(),
            'email_hash' => hash('sha256', uniqid() . '@test.com'),
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now(),
        ]);

        foreach ($touchpoints as $index => $touchpoint) {
            $daysAgo = $touchpoint['days_ago'] ?? (count($touchpoints) - $index);

            CoreCustomerEvent::create([
                'core_customer_id' => $customer->id,
                'event_type' => $touchpoint['value'] > 0 ? 'purchase' : 'page_view',
                'source' => $touchpoint['channel'],
                'is_converted' => $touchpoint['value'] > 0,
                'conversion_value' => $touchpoint['value'] > 0 ? $touchpoint['value'] : null,
                'created_at' => now()->subDays($daysAgo),
            ]);
        }

        return $customer;
    }
}
