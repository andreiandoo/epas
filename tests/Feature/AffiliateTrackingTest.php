<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Microservice;
use App\Models\Tenant;
use App\Services\AffiliateTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected AffiliateTrackingService $service;
    protected Tenant $tenant;
    protected Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AffiliateTrackingService::class);

        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);

        // Create microservice and attach to tenant
        $microservice = Microservice::create([
            'name' => 'Affiliate Tracking',
            'slug' => 'affiliate-tracking',
            'price' => 10.00,
            'pricing_model' => 'one-time',
            'is_active' => true,
        ]);

        $this->tenant->microservices()->attach($microservice->id, [
            'is_active' => true,
            'activated_at' => now(),
            'configuration' => [
                'cookie_name' => 'aff_ref',
                'cookie_duration_days' => 90,
                'commission_type' => 'percent',
                'commission_value' => 10.00,
                'self_purchase_guard' => true,
            ],
        ]);

        // Create test affiliate
        $this->affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST123',
            'name' => 'Test Affiliate',
            'contact_email' => 'affiliate@example.com',
            'status' => 'active',
        ]);
    }

    public function test_track_click_creates_click_record()
    {
        $result = $this->service->trackClick([
            'tenant_id' => $this->tenant->id,
            'affiliate_code' => $this->affiliate->code,
            'url' => 'https://example.com',
            'ip' => '127.0.0.1',
            'user_agent' => 'Test Browser',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals($this->affiliate->code, $result['affiliate_code']);
        $this->assertDatabaseHas('affiliate_clicks', [
            'affiliate_id' => $this->affiliate->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_attribute_order_with_valid_cookie()
    {
        $cookieValue = json_encode([
            'affiliate_code' => $this->affiliate->code,
            'click_id' => 1,
            'timestamp' => now()->timestamp,
        ]);

        $attribution = $this->service->attributeOrder([
            'tenant_id' => $this->tenant->id,
            'cookie_value' => $cookieValue,
            'order_amount' => 100.00,
        ]);

        $this->assertNotNull($attribution);
        $this->assertEquals($this->affiliate->id, $attribution['affiliate_id']);
        $this->assertEquals('link', $attribution['attributed_by']);
        $this->assertEquals(10.00, $attribution['commission_value']); // 10% of 100
    }

    public function test_confirm_order_creates_conversion()
    {
        $cookieValue = json_encode([
            'affiliate_code' => $this->affiliate->code,
            'click_id' => 1,
            'timestamp' => now()->timestamp,
        ]);

        $conversion = $this->service->confirmOrder([
            'tenant_id' => $this->tenant->id,
            'order_ref' => 'ORDER123',
            'order_amount' => 100.00,
            'buyer_email' => 'buyer@example.com',
            'cookie_value' => $cookieValue,
        ]);

        $this->assertNotNull($conversion);
        $this->assertEquals('ORDER123', $conversion->order_ref);
        $this->assertEquals(10.00, $conversion->commission_value);
        $this->assertEquals('pending', $conversion->status);
    }

    public function test_dedup_prevents_duplicate_conversions()
    {
        $cookieValue = json_encode([
            'affiliate_code' => $this->affiliate->code,
            'click_id' => 1,
            'timestamp' => now()->timestamp,
        ]);

        // First conversion
        $conversion1 = $this->service->confirmOrder([
            'tenant_id' => $this->tenant->id,
            'order_ref' => 'ORDER123',
            'order_amount' => 100.00,
            'buyer_email' => 'buyer@example.com',
            'cookie_value' => $cookieValue,
        ]);

        // Attempt duplicate
        $conversion2 = $this->service->confirmOrder([
            'tenant_id' => $this->tenant->id,
            'order_ref' => 'ORDER123',
            'order_amount' => 100.00,
            'buyer_email' => 'buyer@example.com',
            'cookie_value' => $cookieValue,
        ]);

        $this->assertEquals($conversion1->id, $conversion2->id);
        $this->assertEquals(1, AffiliateConversion::where('order_ref', 'ORDER123')->count());
    }

    public function test_self_purchase_guard_blocks_self_purchases()
    {
        $cookieValue = json_encode([
            'affiliate_code' => $this->affiliate->code,
            'click_id' => 1,
            'timestamp' => now()->timestamp,
        ]);

        // Try to create conversion with same email as affiliate
        $conversion = $this->service->confirmOrder([
            'tenant_id' => $this->tenant->id,
            'order_ref' => 'ORDER456',
            'order_amount' => 100.00,
            'buyer_email' => $this->affiliate->contact_email, // Same as affiliate
            'cookie_value' => $cookieValue,
        ]);

        $this->assertNull($conversion); // Should be blocked
    }

    public function test_approve_conversion_changes_status()
    {
        AffiliateConversion::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
            'order_ref' => 'ORDER789',
            'amount' => 100.00,
            'commission_value' => 10.00,
            'commission_type' => 'percent',
            'status' => 'pending',
            'attributed_by' => 'link',
        ]);

        $conversion = $this->service->approveConversion('ORDER789', $this->tenant->id);

        $this->assertNotNull($conversion);
        $this->assertEquals('approved', $conversion->status);
    }

    public function test_reverse_conversion_changes_status()
    {
        AffiliateConversion::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
            'order_ref' => 'ORDER999',
            'amount' => 100.00,
            'commission_value' => 10.00,
            'commission_type' => 'percent',
            'status' => 'approved',
            'attributed_by' => 'link',
        ]);

        $conversion = $this->service->reverseConversion('ORDER999', $this->tenant->id);

        $this->assertNotNull($conversion);
        $this->assertEquals('reversed', $conversion->status);
    }

    public function test_get_affiliate_stats_returns_correct_data()
    {
        // Create test conversions
        AffiliateConversion::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
            'order_ref' => 'ORDER1',
            'amount' => 100.00,
            'commission_value' => 10.00,
            'commission_type' => 'percent',
            'status' => 'approved',
            'attributed_by' => 'link',
        ]);

        AffiliateConversion::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
            'order_ref' => 'ORDER2',
            'amount' => 200.00,
            'commission_value' => 20.00,
            'commission_type' => 'percent',
            'status' => 'pending',
            'attributed_by' => 'coupon',
        ]);

        $stats = $this->service->getAffiliateStats($this->affiliate->id);

        $this->assertEquals(2, $stats['total_conversions']);
        $this->assertEquals(1, $stats['approved_conversions']);
        $this->assertEquals(1, $stats['pending_conversions']);
        $this->assertEquals(10.00, $stats['total_commission']);
        $this->assertEquals(20.00, $stats['pending_commission']);
    }
}
