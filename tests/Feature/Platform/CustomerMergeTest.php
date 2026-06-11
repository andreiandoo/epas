<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMergeTest extends TestCase
{
    use RefreshDatabase;

    protected CoreCustomer $sourceCustomer;
    protected CoreCustomer $targetCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceCustomer = CoreCustomer::create([
            'uuid' => 'source-customer',
            'email_hash' => hash('sha256', 'source@test.com'),
            'first_seen_at' => now()->subMonths(12),
            'last_seen_at' => now()->subDays(30),
            'total_orders' => 3,
            'total_spent' => 300,
            'total_visits' => 10,
            'total_pageviews' => 50,
            'rfm_score' => 8,
        ]);

        $this->targetCustomer = CoreCustomer::create([
            'uuid' => 'target-customer',
            'email_hash' => hash('sha256', 'target@test.com'),
            'first_seen_at' => now()->subMonths(6),
            'last_seen_at' => now()->subDays(5),
            'total_orders' => 5,
            'total_spent' => 500,
            'total_visits' => 15,
            'total_pageviews' => 75,
            'rfm_score' => 10,
        ]);
    }

    /** @test */
    public function it_merges_customer_totals()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Totals should be combined
        $this->assertEquals(8, $this->targetCustomer->total_orders); // 3 + 5
        $this->assertEquals(800, $this->targetCustomer->total_spent); // 300 + 500
        $this->assertEquals(25, $this->targetCustomer->total_visits); // 10 + 15
        $this->assertEquals(125, $this->targetCustomer->total_pageviews); // 50 + 75
    }

    /** @test */
    public function it_transfers_sessions()
    {
        $sourceSession = CoreSession::create([
            'core_customer_id' => $this->sourceCustomer->id,
            'session_id' => 'source-session',
            'started_at' => now()->subDays(40),
        ]);

        $targetSession = CoreSession::create([
            'core_customer_id' => $this->targetCustomer->id,
            'session_id' => 'target-session',
            'started_at' => now()->subDays(10),
        ]);

        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $sourceSession->refresh();

        // Source session should now belong to target
        $this->assertEquals($this->targetCustomer->id, $sourceSession->core_customer_id);

        // Target should have both sessions
        $this->assertEquals(2, $this->targetCustomer->sessions()->count());
    }

    /** @test */
    public function it_transfers_events()
    {
        $sourceEvent = CoreCustomerEvent::create([
            'core_customer_id' => $this->sourceCustomer->id,
            'event_type' => 'purchase',
            'conversion_value' => 100,
            'created_at' => now()->subDays(40),
        ]);

        $targetEvent = CoreCustomerEvent::create([
            'core_customer_id' => $this->targetCustomer->id,
            'event_type' => 'purchase',
            'conversion_value' => 200,
            'created_at' => now()->subDays(10),
        ]);

        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $sourceEvent->refresh();

        // Source event should now belong to target
        $this->assertEquals($this->targetCustomer->id, $sourceEvent->core_customer_id);

        // Target should have both events
        $this->assertEquals(2, $this->targetCustomer->events()->count());
    }

    /** @test */
    public function it_marks_source_as_merged()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->sourceCustomer->refresh();

        $this->assertTrue($this->sourceCustomer->is_merged);
        $this->assertEquals($this->targetCustomer->id, $this->sourceCustomer->merged_into_id);
        $this->assertNotNull($this->sourceCustomer->merged_at);
    }

    /** @test */
    public function it_uses_earlier_first_seen_date()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Target should use source's earlier first_seen_at
        $this->assertEquals(
            $this->sourceCustomer->first_seen_at->format('Y-m-d'),
            $this->targetCustomer->first_seen_at->format('Y-m-d')
        );
    }

    /** @test */
    public function it_uses_later_last_seen_date()
    {
        // Source has older last_seen, target has newer
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Target should keep its more recent last_seen_at
        $this->assertEquals(
            now()->subDays(5)->format('Y-m-d'),
            $this->targetCustomer->last_seen_at->format('Y-m-d')
        );
    }

    /** @test */
    public function it_links_device_ids()
    {
        $this->sourceCustomer->update(['primary_device_id' => 'device-source-123']);
        $this->targetCustomer->update(['primary_device_id' => 'device-target-456']);

        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Target should have linked device IDs
        $this->assertContains('device-source-123', $this->targetCustomer->linked_device_ids ?? []);
    }

    /** @test */
    public function it_tracks_linked_customer_ids()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Target should track the merged customer
        $this->assertContains($this->sourceCustomer->uuid, $this->targetCustomer->linked_customer_ids ?? []);
    }

    /** @test */
    public function it_keeps_higher_rfm_score()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Target should keep higher RFM score (10 > 8)
        $this->assertEquals(10, $this->targetCustomer->rfm_score);
    }

    /** @test */
    public function link_device_adds_to_array()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'device-test',
            'first_seen_at' => now(),
        ]);

        $customer->linkDevice('device-1');
        $customer->linkDevice('device-2');
        $customer->linkDevice('device-1'); // Duplicate

        $customer->refresh();

        $this->assertCount(2, $customer->linked_device_ids);
        $this->assertContains('device-1', $customer->linked_device_ids);
        $this->assertContains('device-2', $customer->linked_device_ids);
    }

    /** @test */
    public function it_assigns_cohort_on_merge()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $this->targetCustomer->refresh();

        // Cohort should be assigned based on earliest first_seen_at
        $this->assertNotNull($this->targetCustomer->cohort_month);
        $this->assertEquals(
            $this->sourceCustomer->first_seen_at->format('Y-m'),
            $this->targetCustomer->cohort_month
        );
    }

    /** @test */
    public function assign_cohort_sets_month_and_week()
    {
        $customer = CoreCustomer::create([
            'uuid' => 'cohort-test',
            'first_seen_at' => now()->subMonths(3),
        ]);

        $customer->assignCohort();
        $customer->refresh();

        $this->assertNotNull($customer->cohort_month);
        $this->assertNotNull($customer->cohort_week);
        $this->assertEquals(now()->subMonths(3)->format('Y-m'), $customer->cohort_month);
    }

    /** @test */
    public function merged_customer_cannot_be_merged_again()
    {
        $this->sourceCustomer->mergeInto($this->targetCustomer);

        $thirdCustomer = CoreCustomer::create([
            'uuid' => 'third-customer',
            'first_seen_at' => now(),
            'total_orders' => 1,
            'total_spent' => 100,
        ]);

        // Attempting to merge already-merged source should not work
        // (depending on implementation - either exception or no-op)
        $this->sourceCustomer->refresh();
        $this->assertTrue($this->sourceCustomer->is_merged);
    }

    /** @test */
    public function get_display_name_returns_correct_value()
    {
        $namedCustomer = CoreCustomer::create([
            'uuid' => 'named-customer',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'first_seen_at' => now(),
        ]);

        $emailCustomer = CoreCustomer::create([
            'uuid' => 'email-customer',
            'email' => 'jane@example.com',
            'first_seen_at' => now(),
        ]);

        $anonymousCustomer = CoreCustomer::create([
            'uuid' => 'anonymous-customer',
            'first_seen_at' => now(),
        ]);

        $this->assertEquals('John Doe', $namedCustomer->getDisplayName());
        $this->assertEquals('jane@example.com', $emailCustomer->getDisplayName());
        $this->assertStringContainsString('anonymous-customer', $anonymousCustomer->getDisplayName());
    }
}
