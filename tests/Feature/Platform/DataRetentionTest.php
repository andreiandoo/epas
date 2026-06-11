<?php

namespace Tests\Feature\Platform;

use App\Jobs\DataRetentionCleanupJob;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\DataRetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected CoreCustomer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CoreCustomer::create([
            'uuid' => 'retention-test-customer',
            'email_hash' => hash('sha256', 'retention@test.com'),
            'first_seen_at' => now()->subYears(2),
        ]);
    }

    /** @test */
    public function it_creates_retention_policy()
    {
        $policy = DataRetentionPolicy::create([
            'data_type' => 'sessions',
            'retention_days' => 90,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        $this->assertNotNull($policy->id);
        $this->assertEquals('sessions', $policy->data_type);
        $this->assertEquals(90, $policy->retention_days);
        $this->assertTrue($policy->is_active);
    }

    /** @test */
    public function it_deletes_old_sessions()
    {
        // Create retention policy
        DataRetentionPolicy::create([
            'data_type' => 'sessions',
            'retention_days' => 30,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        // Create old session (should be deleted)
        CoreSession::create([
            'core_customer_id' => $this->customer->id,
            'session_id' => 'old-session',
            'started_at' => now()->subDays(60),
            'is_converted' => false,
        ]);

        // Create recent session (should be kept)
        CoreSession::create([
            'core_customer_id' => $this->customer->id,
            'session_id' => 'recent-session',
            'started_at' => now()->subDays(10),
            'is_converted' => false,
        ]);

        $this->assertEquals(2, CoreSession::count());

        $job = new DataRetentionCleanupJob();
        $job->handle();

        $this->assertEquals(1, CoreSession::count());
        $this->assertNotNull(CoreSession::where('session_id', 'recent-session')->first());
    }

    /** @test */
    public function it_preserves_converted_sessions()
    {
        DataRetentionPolicy::create([
            'data_type' => 'sessions',
            'retention_days' => 30,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        // Create old but converted session (should be preserved)
        CoreSession::create([
            'core_customer_id' => $this->customer->id,
            'session_id' => 'old-converted-session',
            'started_at' => now()->subDays(60),
            'is_converted' => true,
            'total_value' => 100,
        ]);

        $job = new DataRetentionCleanupJob();
        $job->handle();

        $this->assertEquals(1, CoreSession::count());
        $this->assertNotNull(CoreSession::where('session_id', 'old-converted-session')->first());
    }

    /** @test */
    public function it_deletes_old_events()
    {
        DataRetentionPolicy::create([
            'data_type' => 'events',
            'retention_days' => 60,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        // Create old event
        CoreCustomerEvent::create([
            'core_customer_id' => $this->customer->id,
            'event_type' => 'page_view',
            'is_converted' => false,
            'created_at' => now()->subDays(90),
        ]);

        // Create recent event
        CoreCustomerEvent::create([
            'core_customer_id' => $this->customer->id,
            'event_type' => 'page_view',
            'is_converted' => false,
            'created_at' => now()->subDays(30),
        ]);

        $this->assertEquals(2, CoreCustomerEvent::count());

        $job = new DataRetentionCleanupJob();
        $job->handle();

        $this->assertEquals(1, CoreCustomerEvent::count());
    }

    /** @test */
    public function it_preserves_purchase_events()
    {
        DataRetentionPolicy::create([
            'data_type' => 'events',
            'retention_days' => 30,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        // Create old purchase event (should be preserved)
        CoreCustomerEvent::create([
            'core_customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'is_converted' => true,
            'conversion_value' => 100,
            'created_at' => now()->subDays(90),
        ]);

        $job = new DataRetentionCleanupJob();
        $job->handle();

        $this->assertEquals(1, CoreCustomerEvent::count());
    }

    /** @test */
    public function it_skips_inactive_policies()
    {
        DataRetentionPolicy::create([
            'data_type' => 'sessions',
            'retention_days' => 30,
            'is_active' => false, // Inactive
            'archive_strategy' => 'delete',
        ]);

        CoreSession::create([
            'core_customer_id' => $this->customer->id,
            'session_id' => 'old-session',
            'started_at' => now()->subDays(60),
            'is_converted' => false,
        ]);

        $job = new DataRetentionCleanupJob();
        $job->handle();

        // Session should not be deleted because policy is inactive
        $this->assertEquals(1, CoreSession::count());
    }

    /** @test */
    public function it_updates_policy_cleanup_stats()
    {
        $policy = DataRetentionPolicy::create([
            'data_type' => 'sessions',
            'retention_days' => 30,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        // Create sessions to delete
        for ($i = 0; $i < 5; $i++) {
            CoreSession::create([
                'core_customer_id' => $this->customer->id,
                'session_id' => "old-session-{$i}",
                'started_at' => now()->subDays(60),
                'is_converted' => false,
            ]);
        }

        $job = new DataRetentionCleanupJob();
        $job->handle();

        $policy->refresh();

        $this->assertNotNull($policy->last_cleanup_at);
        $this->assertEquals(5, $policy->last_cleanup_count);
    }

    /** @test */
    public function it_handles_anonymize_strategy()
    {
        DataRetentionPolicy::create([
            'data_type' => 'events',
            'retention_days' => 30,
            'is_active' => true,
            'archive_strategy' => 'anonymize',
        ]);

        // Create old event with personal data
        $event = CoreCustomerEvent::create([
            'core_customer_id' => $this->customer->id,
            'event_type' => 'page_view',
            'is_converted' => false,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'created_at' => now()->subDays(60),
        ]);

        $job = new DataRetentionCleanupJob();
        $job->handle();

        $event->refresh();

        // Event should be anonymized, not deleted
        $this->assertNull($event->ip_address);
        $this->assertNull($event->user_agent);
    }

    /** @test */
    public function retention_policy_has_correct_data_types()
    {
        $dataTypes = DataRetentionPolicy::DATA_TYPES;

        $this->assertContains('sessions', $dataTypes);
        $this->assertContains('events', $dataTypes);
        $this->assertContains('conversions', $dataTypes);
        $this->assertContains('pageviews', $dataTypes);
    }

    /** @test */
    public function retention_policy_has_correct_strategies()
    {
        $strategies = DataRetentionPolicy::ARCHIVE_STRATEGIES;

        $this->assertArrayHasKey('delete', $strategies);
        $this->assertArrayHasKey('archive', $strategies);
        $this->assertArrayHasKey('anonymize', $strategies);
    }

    /** @test */
    public function cutoff_date_calculation_is_correct()
    {
        $policy = DataRetentionPolicy::create([
            'data_type' => 'sessions',
            'retention_days' => 90,
            'is_active' => true,
            'archive_strategy' => 'delete',
        ]);

        $cutoff = $policy->getCutoffDate();

        $expectedCutoff = now()->subDays(90);

        $this->assertEquals(
            $expectedCutoff->format('Y-m-d'),
            $cutoff->format('Y-m-d')
        );
    }
}
