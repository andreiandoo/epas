<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TenantNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_notification()
    {
        $service = app(NotificationService::class);

        $notification = $service->notify(
            'test_tenant',
            TenantNotification::TYPE_SYSTEM_ALERT,
            'Test Alert',
            'This is a test notification',
            ['priority' => TenantNotification::PRIORITY_HIGH]
        );

        $this->assertInstanceOf(TenantNotification::class, $notification);
        $this->assertEquals('test_tenant', $notification->tenant_id);
        $this->assertEquals('Test Alert', $notification->title);
        $this->assertEquals(TenantNotification::PRIORITY_HIGH, $notification->priority);
    }

    /** @test */
    public function it_can_mark_notification_as_read()
    {
        $notification = TenantNotification::create([
            'tenant_id' => 'test_tenant',
            'type' => TenantNotification::TYPE_SYSTEM_ALERT,
            'priority' => TenantNotification::PRIORITY_MEDIUM,
            'title' => 'Test',
            'message' => 'Test message',
            'status' => TenantNotification::STATUS_UNREAD,
        ]);

        $this->assertTrue($notification->isUnread());

        $notification->markAsRead();

        $this->assertFalse($notification->fresh()->isUnread());
        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function it_sends_microservice_expiring_notification()
    {
        Mail::fake();

        $service = app(NotificationService::class);

        // Create microservice first
        \DB::table('microservices')->insert([
            'id' => 1,
            'slug' => 'test-service',
            'name' => 'Test Service',
            'description' => 'Test',
            'price' => 1.00,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'pricing_model' => 'recurring',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscriptionData = [
            'id' => 1,
            'microservice_id' => 1,
            'expires_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ];

        $service->notifyMicroserviceExpiring('test_tenant', $subscriptionData);

        // Check notification was created
        $notification = TenantNotification::first();
        $this->assertNotNull($notification);
        $this->assertEquals(TenantNotification::TYPE_MICROSERVICE_EXPIRING, $notification->type);
        $this->assertEquals(TenantNotification::PRIORITY_HIGH, $notification->priority);
    }

    /** @test */
    public function it_sends_efactura_rejected_notification()
    {
        $service = app(NotificationService::class);

        $queueData = [
            'id' => 1,
            'invoice_id' => 123,
            'error_message' => 'Invalid VAT number',
        ];

        $service->notifyEFacturaRejected('test_tenant', $queueData);

        $notification = TenantNotification::first();
        $this->assertNotNull($notification);
        $this->assertEquals(TenantNotification::TYPE_EFACTURA_REJECTED, $notification->type);
        $this->assertEquals(TenantNotification::PRIORITY_HIGH, $notification->priority);
        $this->assertStringContainsString('rejected by ANAF', $notification->message);
    }

    /** @test */
    public function it_counts_unread_notifications_correctly()
    {
        // Create some notifications
        TenantNotification::create([
            'tenant_id' => 'test_tenant',
            'type' => TenantNotification::TYPE_SYSTEM_ALERT,
            'priority' => TenantNotification::PRIORITY_LOW,
            'title' => 'Test 1',
            'message' => 'Message 1',
            'status' => TenantNotification::STATUS_UNREAD,
        ]);

        TenantNotification::create([
            'tenant_id' => 'test_tenant',
            'type' => TenantNotification::TYPE_SYSTEM_ALERT,
            'priority' => TenantNotification::PRIORITY_LOW,
            'title' => 'Test 2',
            'message' => 'Message 2',
            'status' => TenantNotification::STATUS_UNREAD,
        ]);

        TenantNotification::create([
            'tenant_id' => 'test_tenant',
            'type' => TenantNotification::TYPE_SYSTEM_ALERT,
            'priority' => TenantNotification::PRIORITY_LOW,
            'title' => 'Test 3',
            'message' => 'Message 3',
            'status' => TenantNotification::STATUS_READ,
        ]);

        $service = app(NotificationService::class);
        $unreadCount = $service->getUnreadCount('test_tenant');

        $this->assertEquals(2, $unreadCount);
    }

    /** @test */
    public function it_can_mark_all_as_read()
    {
        // Create unread notifications
        TenantNotification::create([
            'tenant_id' => 'test_tenant',
            'type' => TenantNotification::TYPE_SYSTEM_ALERT,
            'priority' => TenantNotification::PRIORITY_LOW,
            'title' => 'Test 1',
            'message' => 'Message 1',
            'status' => TenantNotification::STATUS_UNREAD,
        ]);

        TenantNotification::create([
            'tenant_id' => 'test_tenant',
            'type' => TenantNotification::TYPE_SYSTEM_ALERT,
            'priority' => TenantNotification::PRIORITY_LOW,
            'title' => 'Test 2',
            'message' => 'Message 2',
            'status' => TenantNotification::STATUS_UNREAD,
        ]);

        $service = app(NotificationService::class);
        $service->markAllAsRead('test_tenant');

        $unreadCount = $service->getUnreadCount('test_tenant');
        $this->assertEquals(0, $unreadCount);
    }
}
