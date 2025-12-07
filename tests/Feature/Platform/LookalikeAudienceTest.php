<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\PlatformAdAccount;
use App\Models\Platform\PlatformAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookalikeAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformAdAccount $adAccount;
    protected PlatformAudience $sourceAudience;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adAccount = PlatformAdAccount::create([
            'account_name' => 'Test Facebook Account',
            'platform' => PlatformAdAccount::PLATFORM_FACEBOOK,
            'account_id' => 'fb-test-123',
            'pixel_id' => '123456789',
            'access_token' => 'test-token',
            'token_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // Create source audience with members
        $this->sourceAudience = PlatformAudience::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'name' => 'High Value Customers',
            'audience_type' => PlatformAudience::TYPE_HIGH_VALUE,
            'status' => PlatformAudience::STATUS_ACTIVE,
            'member_count' => 1000,
            'matched_count' => 800,
        ]);

        // Create some test customers
        for ($i = 0; $i < 50; $i++) {
            CoreCustomer::create([
                'uuid' => "test-customer-{$i}",
                'email_hash' => hash('sha256', "customer{$i}@test.com"),
                'rfm_score' => rand(10, 15),
                'total_spent' => rand(500, 5000),
                'total_orders' => rand(3, 20),
                'first_seen_at' => now()->subMonths(rand(1, 12)),
            ]);
        }
    }

    /** @test */
    public function it_can_create_lookalike_audience_from_source()
    {
        $lookalike = $this->sourceAudience->createLookalike(
            $this->adAccount,
            5,
            'US',
            'High Value 5% US Lookalike'
        );

        $this->assertNotNull($lookalike);
        $this->assertEquals(PlatformAudience::TYPE_LOOKALIKE, $lookalike->audience_type);
        $this->assertEquals(PlatformAudience::LOOKALIKE_SOURCE_AUDIENCE, $lookalike->lookalike_source_type);
        $this->assertEquals($this->sourceAudience->id, $lookalike->lookalike_source_audience_id);
        $this->assertEquals(5, $lookalike->lookalike_percentage);
        $this->assertEquals('US', $lookalike->lookalike_country);
        $this->assertEquals('High Value 5% US Lookalike', $lookalike->name);
    }

    /** @test */
    public function it_auto_generates_name_for_lookalike()
    {
        $lookalike = $this->sourceAudience->createLookalike(
            $this->adAccount,
            3,
            'GB'
        );

        $this->assertStringContainsString('High Value Customers', $lookalike->name);
        $this->assertStringContainsString('3%', $lookalike->name);
        $this->assertStringContainsString('GB', $lookalike->name);
    }

    /** @test */
    public function it_identifies_lookalike_audience_correctly()
    {
        $lookalike = $this->sourceAudience->createLookalike($this->adAccount, 1, 'US');

        $this->assertTrue($lookalike->isLookalike());
        $this->assertFalse($this->sourceAudience->isLookalike());
    }

    /** @test */
    public function it_returns_source_audience_relationship()
    {
        $lookalike = $this->sourceAudience->createLookalike($this->adAccount, 1, 'US');

        $this->assertNotNull($lookalike->sourceAudience);
        $this->assertEquals($this->sourceAudience->id, $lookalike->sourceAudience->id);
    }

    /** @test */
    public function it_returns_derived_lookalikes_relationship()
    {
        $this->sourceAudience->createLookalike($this->adAccount, 1, 'US');
        $this->sourceAudience->createLookalike($this->adAccount, 5, 'US');
        $this->sourceAudience->createLookalike($this->adAccount, 1, 'GB');

        $this->sourceAudience->refresh();

        $this->assertCount(3, $this->sourceAudience->derivedLookalikes);
    }

    /** @test */
    public function it_gets_seed_query_for_purchasers_source()
    {
        $lookalike = PlatformAudience::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'name' => 'Purchasers Lookalike',
            'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
            'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_PURCHASERS,
            'lookalike_percentage' => 1,
            'lookalike_country' => 'US',
            'status' => PlatformAudience::STATUS_DRAFT,
        ]);

        $query = $lookalike->getLookalikeSeedQuery();

        $this->assertNotNull($query);
        // All our test customers have orders > 0
        $this->assertGreaterThan(0, $query->count());
    }

    /** @test */
    public function it_gets_seed_query_for_high_value_source()
    {
        $lookalike = PlatformAudience::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'name' => 'High Value Lookalike',
            'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
            'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_HIGH_VALUE,
            'lookalike_percentage' => 1,
            'lookalike_country' => 'US',
            'status' => PlatformAudience::STATUS_DRAFT,
        ]);

        $count = $lookalike->getLookalikeSeedCount();

        // Should only include customers with rfm_score >= 12
        $expectedCount = CoreCustomer::where('rfm_score', '>=', 12)
            ->where(fn($q) => $q->whereNotNull('email_hash')->orWhereNotNull('phone_hash'))
            ->count();

        $this->assertEquals($expectedCount, $count);
    }

    /** @test */
    public function it_estimates_lookalike_reach()
    {
        $lookalike = $this->sourceAudience->createLookalike($this->adAccount, 5, 'US');

        $estimate = $lookalike->estimateLookalikeReach();

        $this->assertArrayHasKey('seed_count', $estimate);
        $this->assertArrayHasKey('percentage', $estimate);
        $this->assertArrayHasKey('country', $estimate);
        $this->assertArrayHasKey('estimated_reach_min', $estimate);
        $this->assertArrayHasKey('estimated_reach_max', $estimate);
        $this->assertArrayHasKey('quality_indicator', $estimate);
        $this->assertArrayHasKey('recommendation', $estimate);

        $this->assertEquals(5, $estimate['percentage']);
        $this->assertEquals('US', $estimate['country']);
        $this->assertGreaterThan(0, $estimate['estimated_reach_min']);
    }

    /** @test */
    public function it_builds_lookalike_config_for_api()
    {
        $lookalike = $this->sourceAudience->createLookalike($this->adAccount, 3, 'GB');

        $config = $lookalike->buildLookalikeConfig();

        $this->assertArrayHasKey('source_type', $config);
        $this->assertArrayHasKey('percentage', $config);
        $this->assertArrayHasKey('country', $config);
        $this->assertArrayHasKey('seed_count', $config);
        $this->assertArrayHasKey('seed_customers', $config);

        $this->assertEquals(3, $config['percentage']);
        $this->assertEquals('GB', $config['country']);
    }

    /** @test */
    public function it_filters_audiences_that_can_be_seed()
    {
        // Create a lookalike (should not be usable as seed)
        $lookalike = $this->sourceAudience->createLookalike($this->adAccount, 1, 'US');

        // Create a small audience (should not be usable as seed)
        $smallAudience = PlatformAudience::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'name' => 'Small Audience',
            'audience_type' => PlatformAudience::TYPE_PURCHASERS,
            'status' => PlatformAudience::STATUS_ACTIVE,
            'member_count' => 50, // Less than 100
        ]);

        $seedableAudiences = PlatformAudience::canBeSeed()->get();

        $this->assertTrue($seedableAudiences->contains('id', $this->sourceAudience->id));
        $this->assertFalse($seedableAudiences->contains('id', $lookalike->id));
        $this->assertFalse($seedableAudiences->contains('id', $smallAudience->id));
    }

    /** @test */
    public function it_returns_lookalike_percentage_options()
    {
        $options = PlatformAudience::getLookalikePercentageOptions();

        $this->assertCount(10, $options);
        $this->assertArrayHasKey(1, $options);
        $this->assertArrayHasKey(10, $options);
        $this->assertStringContainsString('Most Similar', $options[1]);
        $this->assertStringContainsString('Broadest Reach', $options[10]);
    }

    /** @test */
    public function it_returns_lookalike_country_options()
    {
        $options = PlatformAudience::getLookalikeCountryOptions();

        $this->assertArrayHasKey('US', $options);
        $this->assertArrayHasKey('GB', $options);
        $this->assertArrayHasKey('CA', $options);
        $this->assertEquals('United States', $options['US']);
    }

    /** @test */
    public function it_returns_correct_lookalike_source_label()
    {
        $lookalike = PlatformAudience::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'name' => 'Test Lookalike',
            'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
            'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_HIGH_VALUE,
            'lookalike_percentage' => 1,
            'lookalike_country' => 'US',
            'status' => PlatformAudience::STATUS_DRAFT,
        ]);

        $label = $lookalike->getLookalikeSourceLabel();

        $this->assertEquals('High-Value Customers (RFM 12+)', $label);
    }

    /** @test */
    public function it_returns_source_audience_name_as_label()
    {
        $lookalike = $this->sourceAudience->createLookalike($this->adAccount, 1, 'US');

        $label = $lookalike->getLookalikeSourceLabel();

        $this->assertEquals('High Value Customers', $label);
    }

    /** @test */
    public function lookalike_scope_filters_correctly()
    {
        $this->sourceAudience->createLookalike($this->adAccount, 1, 'US');
        $this->sourceAudience->createLookalike($this->adAccount, 5, 'GB');

        $lookalikes = PlatformAudience::lookalike()->get();

        $this->assertCount(2, $lookalikes);
        $this->assertTrue($lookalikes->every(fn($a) => $a->audience_type === PlatformAudience::TYPE_LOOKALIKE));
    }
}
