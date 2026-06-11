<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\WhatsAppOptIn;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSchedule;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WhatsAppMicroserviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create approved template
        WhatsAppTemplate::create([
            'tenant_id' => 'test_tenant',
            'name' => 'order_confirmation',
            'language' => 'ro',
            'category' => 'order_confirm',
            'body' => 'Hello {first_name}, your order {order_code} is confirmed!',
            'variables' => ['first_name', 'order_code'],
            'status' => WhatsAppTemplate::STATUS_APPROVED,
        ]);

        // Create opt-in for test phone
        WhatsAppOptIn::create([
            'tenant_id' => 'test_tenant',
            'phone_e164' => '+40722123456',
            'status' => WhatsAppOptIn::STATUS_OPT_IN,
            'source' => 'test',
            'consented_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_send_order_confirmation_idempotently()
    {
        $service = app(WhatsAppService::class);

        $orderData = [
            'template_name' => 'order_confirmation',
            'customer_phone' => '+40722123456',
            'customer_first_name' => 'Ion',
            'order_code' => 'ORD-001',
        ];

        // First send
        $result1 = $service->sendOrderConfirmation('test_tenant', 'ORD-001', $orderData);
        $this->assertTrue($result1['success']);

        // Second send (should be idempotent)
        $result2 = $service->sendOrderConfirmation('test_tenant', 'ORD-001', $orderData);
        $this->assertTrue($result2['success']);
        $this->assertEquals('already_sent', $result2['status']);

        // Should only have one message in database
        $this->assertCount(1, WhatsAppMessage::all());
    }

    /** @test */
    public function it_skips_sending_if_user_not_opted_in()
    {
        $service = app(WhatsAppService::class);

        $orderData = [
            'template_name' => 'order_confirmation',
            'customer_phone' => '+40722999999', // Not opted in
            'customer_first_name' => 'Maria',
            'order_code' => 'ORD-002',
        ];

        $result = $service->sendOrderConfirmation('test_tenant', 'ORD-002', $orderData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('fallback', $result);
        $this->assertEquals('email', $result['fallback']);
    }

    /** @test */
    public function it_schedules_event_reminders_correctly()
    {
        $service = app(WhatsAppService::class);

        $eventData = [
            'template_name' => 'event_reminder',
            'event_start_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'customer_phone' => '+40722123456',
            'customer_first_name' => 'Ion',
            'event_name' => 'Concert',
            'event_date' => '20 Dec 2025',
            'event_time' => '19:00',
            'venue_name' => 'Arena',
        ];

        $result = $service->scheduleReminders('test_tenant', 'ORD-001', $eventData);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['scheduled_count']);

        // Check schedules were created
        $schedules = WhatsAppSchedule::forTenant('test_tenant')->get();
        $this->assertGreaterThan(0, $schedules->count());
    }

    /** @test */
    public function it_normalizes_phone_numbers_to_e164()
    {
        $normalized = WhatsAppOptIn::normalizePhone('0722123456', '+40');
        $this->assertEquals('+40722123456', $normalized);

        $normalized2 = WhatsAppOptIn::normalizePhone('+40722123456');
        $this->assertEquals('+40722123456', $normalized2);
    }

    /** @test */
    public function it_can_process_scheduled_reminders()
    {
        $service = app(WhatsAppService::class);

        // Create a schedule that's ready to run
        WhatsAppSchedule::create([
            'tenant_id' => 'test_tenant',
            'message_type' => WhatsAppSchedule::TYPE_REMINDER_D7,
            'run_at' => now()->subMinute(),
            'correlation_ref' => 'ORD-001',
            'payload' => [
                'phone' => '+40722123456',
                'template_name' => 'event_reminder',
                'variables' => ['first_name' => 'Ion'],
            ],
            'status' => WhatsAppSchedule::STATUS_SCHEDULED,
        ]);

        $result = $service->processScheduledReminders(10);

        $this->assertEquals(1, $result['processed']);
        $this->assertGreaterThan(0, $result['sent'] + $result['skipped']);
    }
}
