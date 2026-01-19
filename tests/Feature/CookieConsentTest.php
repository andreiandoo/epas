<?php

namespace Tests\Feature;

use App\Models\CookieConsent;
use App\Models\CookieConsentHistory;
use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_creates_cookie_consent_record()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-123',
            'necessary' => true,
            'analytics' => true,
            'marketing' => false,
            'preferences' => true,
            'action' => 'customize',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->assertNotNull($consent->id);
        $this->assertTrue($consent->necessary);
        $this->assertTrue($consent->analytics);
        $this->assertFalse($consent->marketing);
        $this->assertTrue($consent->preferences);
    }

    /** @test */
    public function it_links_consent_to_customer()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'visitor_id' => 'test-visitor-456',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $this->assertEquals($this->customer->id, $consent->customer_id);
        $this->assertEquals($this->customer->id, $consent->customer->id);
    }

    /** @test */
    public function it_checks_consent_for_category()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-789',
            'necessary' => true,
            'analytics' => true,
            'marketing' => false,
            'preferences' => false,
            'action' => 'customize',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $this->assertTrue($consent->hasConsent('necessary'));
        $this->assertTrue($consent->hasConsent('analytics'));
        $this->assertFalse($consent->hasConsent('marketing'));
        $this->assertFalse($consent->hasConsent('preferences'));
    }

    /** @test */
    public function it_checks_if_consent_is_valid()
    {
        $validConsent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-valid',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $expiredConsent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-expired',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($validConsent->isValid());
        $this->assertFalse($expiredConsent->isValid());
    }

    /** @test */
    public function it_withdraws_consent()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-withdraw',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $consent->withdraw('settings', '192.168.1.2', 'Mozilla/5.0');

        $this->assertNotNull($consent->withdrawn_at);
        $this->assertFalse($consent->analytics);
        $this->assertFalse($consent->marketing);
        $this->assertFalse($consent->preferences);
        $this->assertFalse($consent->isValid());
    }

    /** @test */
    public function it_creates_history_on_consent_update()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-history',
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'action' => 'reject_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        // Create initial history record
        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => null,
            'previous_marketing' => null,
            'previous_preferences' => null,
            'new_analytics' => false,
            'new_marketing' => false,
            'new_preferences' => false,
            'change_type' => CookieConsentHistory::TYPE_INITIAL,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'change_source' => 'banner',
            'changed_at' => now(),
        ]);

        // Update consent
        $consent->updateConsent(
            analytics: true,
            marketing: true,
            preferences: false,
            action: 'customize',
            ip: '192.168.1.2',
            userAgent: 'Mozilla/5.0',
            source: 'settings'
        );

        $this->assertTrue($consent->analytics);
        $this->assertTrue($consent->marketing);
        $this->assertFalse($consent->preferences);

        // Check history was created
        $history = CookieConsentHistory::where('cookie_consent_id', $consent->id)
            ->where('change_type', CookieConsentHistory::TYPE_UPDATE)
            ->first();

        $this->assertNotNull($history);
        $this->assertFalse($history->previous_analytics);
        $this->assertTrue($history->new_analytics);
    }

    /** @test */
    public function it_finds_or_creates_consent_for_visitor()
    {
        $visitorId = 'unique-visitor-' . uniqid();

        // First call should create
        $consent1 = CookieConsent::findOrCreateForVisitor(
            $this->tenant->id,
            $visitorId,
            null
        );

        $this->assertNotNull($consent1);

        // Second call should find existing
        $consent2 = CookieConsent::findOrCreateForVisitor(
            $this->tenant->id,
            $visitorId,
            null
        );

        $this->assertEquals($consent1->id, $consent2->id);
    }

    /** @test */
    public function consent_history_tracks_changes_correctly()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-changes',
            'necessary' => true,
            'analytics' => true,
            'marketing' => false,
            'preferences' => true,
            'action' => 'customize',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $history = CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => false,
            'previous_marketing' => false,
            'previous_preferences' => false,
            'new_analytics' => true,
            'new_marketing' => false,
            'new_preferences' => true,
            'change_type' => CookieConsentHistory::TYPE_UPDATE,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'change_source' => 'banner',
            'changed_at' => now(),
        ]);

        $changes = $history->getChangesArray();

        $this->assertArrayHasKey('analytics', $changes);
        $this->assertArrayHasKey('preferences', $changes);
        $this->assertArrayNotHasKey('marketing', $changes); // No change

        $this->assertFalse($changes['analytics']['from']);
        $this->assertTrue($changes['analytics']['to']);
    }

    /** @test */
    public function consent_history_detects_opt_in()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-optin',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $history = CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => false,
            'previous_marketing' => false,
            'previous_preferences' => false,
            'new_analytics' => true,
            'new_marketing' => true,
            'new_preferences' => true,
            'change_type' => CookieConsentHistory::TYPE_UPDATE,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'change_source' => 'banner',
            'changed_at' => now(),
        ]);

        $this->assertTrue($history->isOptIn());
        $this->assertFalse($history->isOptOut());
    }

    /** @test */
    public function consent_history_detects_opt_out()
    {
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'test-visitor-optout',
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'action' => 'reject_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $history = CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => true,
            'previous_marketing' => true,
            'previous_preferences' => true,
            'new_analytics' => false,
            'new_marketing' => false,
            'new_preferences' => false,
            'change_type' => CookieConsentHistory::TYPE_WITHDRAWAL,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Agent',
            'change_source' => 'settings',
            'changed_at' => now(),
        ]);

        $this->assertFalse($history->isOptIn());
        $this->assertTrue($history->isOptOut());
    }

    /** @test */
    public function it_filters_consent_by_tenant()
    {
        $otherTenant = Tenant::factory()->create();

        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'tenant-1-visitor',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        CookieConsent::create([
            'tenant_id' => $otherTenant->id,
            'visitor_id' => 'tenant-2-visitor',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $tenant1Consents = CookieConsent::where('tenant_id', $this->tenant->id)->get();
        $tenant2Consents = CookieConsent::where('tenant_id', $otherTenant->id)->get();

        $this->assertCount(1, $tenant1Consents);
        $this->assertCount(1, $tenant2Consents);
        $this->assertEquals('tenant-1-visitor', $tenant1Consents->first()->visitor_id);
        $this->assertEquals('tenant-2-visitor', $tenant2Consents->first()->visitor_id);
    }

    /** @test */
    public function it_finds_active_consents_only()
    {
        // Active consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'active-visitor',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        // Withdrawn consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'withdrawn-visitor',
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'action' => 'reject_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now()->subMonth(),
            'withdrawn_at' => now(),
        ]);

        // Expired consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => 'expired-visitor',
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '192.168.1.1',
            'consent_source' => 'banner',
            'consented_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $activeConsents = CookieConsent::where('tenant_id', $this->tenant->id)
            ->whereNull('withdrawn_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $this->assertCount(1, $activeConsents);
        $this->assertEquals('active-visitor', $activeConsents->first()->visitor_id);
    }
}
