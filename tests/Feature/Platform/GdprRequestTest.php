<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\GdprRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprRequestTest extends TestCase
{
    use RefreshDatabase;

    protected CoreCustomer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CoreCustomer::create([
            'uuid' => 'gdpr-test-customer',
            'email' => 'gdpr-test@example.com',
            'email_hash' => hash('sha256', 'gdpr-test@example.com'),
            'phone' => '+1234567890',
            'phone_hash' => hash('sha256', '+1234567890'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'first_seen_at' => now()->subMonths(6),
            'last_seen_at' => now()->subDays(5),
            'total_orders' => 5,
            'total_spent' => 500,
            'rfm_score' => 10,
        ]);

        // Create associated data
        CoreSession::create([
            'core_customer_id' => $this->customer->id,
            'session_id' => 'gdpr-session-1',
            'started_at' => now()->subDays(10),
            'ip_address' => '192.168.1.1',
        ]);

        CoreCustomerEvent::create([
            'core_customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 100,
            'created_at' => now()->subDays(5),
        ]);
    }

    /** @test */
    public function it_creates_gdpr_export_request()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->assertNotNull($request->id);
        $this->assertEquals(GdprRequest::TYPE_EXPORT, $request->request_type);
        $this->assertTrue($request->isPending());
    }

    /** @test */
    public function it_creates_gdpr_deletion_request()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_DELETION,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->assertEquals(GdprRequest::TYPE_DELETION, $request->request_type);
    }

    /** @test */
    public function it_exports_customer_personal_data()
    {
        $exportData = $this->customer->exportPersonalData();

        $this->assertIsArray($exportData);
        $this->assertArrayHasKey('customer', $exportData);
        $this->assertArrayHasKey('sessions', $exportData);
        $this->assertArrayHasKey('events', $exportData);

        $customerData = $exportData['customer'];
        $this->assertEquals('John', $customerData['first_name']);
        $this->assertEquals('Doe', $customerData['last_name']);
        $this->assertEquals('gdpr-test@example.com', $customerData['email']);
        $this->assertEquals(5, $customerData['total_orders']);
    }

    /** @test */
    public function it_anonymizes_customer_for_gdpr()
    {
        $customerId = $this->customer->id;

        $this->customer->anonymizeForGdpr();
        $this->customer->refresh();

        // Personal data should be anonymized
        $this->assertNull($this->customer->email);
        $this->assertNull($this->customer->phone);
        $this->assertNull($this->customer->first_name);
        $this->assertNull($this->customer->last_name);
        $this->assertStringStartsWith('anonymized_', $this->customer->email_hash);
        $this->assertTrue($this->customer->is_anonymized);
        $this->assertNotNull($this->customer->anonymized_at);

        // Aggregate data should be preserved
        $this->assertEquals(5, $this->customer->total_orders);
        $this->assertEquals(500, $this->customer->total_spent);
    }

    /** @test */
    public function it_processes_export_request()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $request->process();
        $request->refresh();

        $this->assertTrue($request->isCompleted());
        $this->assertNotNull($request->export_data);
        $this->assertNotNull($request->completed_at);
        $this->assertArrayHasKey('customer', $request->export_data);
    }

    /** @test */
    public function it_processes_deletion_request()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_DELETION,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $request->process();
        $request->refresh();

        $this->assertTrue($request->isCompleted());
        $this->assertNotNull($request->completed_at);

        // Customer should be anonymized
        $this->customer->refresh();
        $this->assertTrue($this->customer->is_anonymized);
    }

    /** @test */
    public function request_status_transitions_correctly()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->assertTrue($request->isPending());
        $this->assertFalse($request->isProcessing());
        $this->assertFalse($request->isCompleted());

        $request->markProcessing();
        $this->assertTrue($request->isProcessing());

        $request->markCompleted();
        $this->assertTrue($request->isCompleted());
    }

    /** @test */
    public function it_tracks_affected_data()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_DELETION,
            'customer_id' => $this->customer->id,
            'request_source' => 'admin',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
            'affected_data' => [
                'sessions' => 1,
                'events' => 1,
                'orders' => 5,
            ],
        ]);

        $this->assertEquals(1, $request->affected_data['sessions']);
        $this->assertEquals(1, $request->affected_data['events']);
    }

    /** @test */
    public function it_supports_different_request_sources()
    {
        $customerRequest = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => GdprRequest::SOURCE_CUSTOMER,
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $adminRequest = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => GdprRequest::SOURCE_ADMIN,
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->assertEquals('customer', $customerRequest->request_source);
        $this->assertEquals('admin', $adminRequest->request_source);
    }

    /** @test */
    public function pending_scope_filters_correctly()
    {
        GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_COMPLETED,
            'requested_at' => now(),
        ]);

        $pending = GdprRequest::pending()->get();
        $this->assertCount(1, $pending);
    }

    /** @test */
    public function it_can_add_notes_to_request()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
            'notes' => 'Customer requested export via email on 2025-01-15',
        ]);

        $this->assertStringContainsString('email', $request->notes);
    }

    /** @test */
    public function rectification_request_updates_customer_data()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_RECTIFICATION,
            'customer_id' => $this->customer->id,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
            'affected_data' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ],
        ]);

        $request->process();

        $this->customer->refresh();

        $this->assertEquals('Jane', $this->customer->first_name);
        $this->assertEquals('Smith', $this->customer->last_name);
    }

    /** @test */
    public function customer_relationship_works()
    {
        $request = GdprRequest::create([
            'request_type' => GdprRequest::TYPE_EXPORT,
            'customer_id' => $this->customer->id,
            'request_source' => 'customer',
            'status' => GdprRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->assertNotNull($request->customer);
        $this->assertEquals($this->customer->id, $request->customer->id);
    }
}
