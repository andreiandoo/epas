<?php

namespace Tests\Feature;

use App\Models\CookieConsent;
use App\Models\CookieConsentHistory;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected TenantDomain $domain;
    protected string $visitorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->domain = TenantDomain::factory()->create([
            'tenant_id' => $this->tenant->id,
            'domain' => 'test.example.com',
            'is_primary' => true,
        ]);

        $this->visitorId = 'api-test-visitor-' . uniqid();
    }

    protected function getTenantClientHeaders(): array
    {
        return [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Domain-ID' => $this->domain->id,
            'Content-Type' => 'application/json',
        ];
    }

    /** @test */
    public function it_returns_no_consent_for_new_visitor()
    {
        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->getJson("/api/tenant-client/consent?visitor_id={$this->visitorId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_consent' => false,
                    'consent' => null,
                ],
            ]);
    }

    /** @test */
    public function it_saves_consent_preferences()
    {
        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent', [
                'visitor_id' => $this->visitorId,
                'analytics' => true,
                'marketing' => false,
                'preferences' => true,
                'action' => 'customize',
                'consent_version' => '1.0',
                'page_url' => 'https://test.example.com/events',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Consent saved successfully.',
            ]);

        $this->assertDatabaseHas('cookie_consents', [
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'analytics' => true,
            'marketing' => false,
            'preferences' => true,
        ]);
    }

    /** @test */
    public function it_retrieves_existing_consent()
    {
        // First, save consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => false,
            'action' => 'customize',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->getJson("/api/tenant-client/consent?visitor_id={$this->visitorId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_consent' => true,
                    'consent' => [
                        'necessary' => true,
                        'analytics' => true,
                        'marketing' => true,
                        'preferences' => false,
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_updates_existing_consent()
    {
        // Create initial consent
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'action' => 'reject_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        // Update consent
        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent', [
                'visitor_id' => $this->visitorId,
                'analytics' => true,
                'marketing' => true,
                'preferences' => true,
                'action' => 'accept_all',
                'consent_version' => '1.0',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Consent updated successfully.',
            ]);

        $consent->refresh();
        $this->assertTrue($consent->analytics);
        $this->assertTrue($consent->marketing);
        $this->assertTrue($consent->preferences);
    }

    /** @test */
    public function it_creates_history_on_update()
    {
        // Create initial consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
            'action' => 'reject_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        // Update consent
        $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent', [
                'visitor_id' => $this->visitorId,
                'analytics' => true,
                'marketing' => false,
                'preferences' => true,
                'action' => 'customize',
            ]);

        $this->assertDatabaseHas('cookie_consent_history', [
            'previous_analytics' => false,
            'new_analytics' => true,
            'change_type' => 'update',
        ]);
    }

    /** @test */
    public function it_withdraws_consent()
    {
        // Create consent
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent/withdraw', [
                'visitor_id' => $this->visitorId,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Consent withdrawn successfully.',
            ]);

        $consent->refresh();
        $this->assertNotNull($consent->withdrawn_at);
        $this->assertFalse($consent->analytics);
        $this->assertFalse($consent->marketing);
        $this->assertFalse($consent->preferences);
    }

    /** @test */
    public function it_returns_error_for_missing_visitor_id_on_withdraw()
    {
        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent/withdraw', []);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Visitor ID or customer authentication required.',
            ]);
    }

    /** @test */
    public function it_returns_consent_history()
    {
        // Create consent
        $consent = CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        // Create history records
        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => null,
            'previous_marketing' => null,
            'previous_preferences' => null,
            'new_analytics' => true,
            'new_marketing' => true,
            'new_preferences' => true,
            'change_type' => 'initial',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'change_source' => 'banner',
            'changed_at' => now()->subHour(),
        ]);

        CookieConsentHistory::create([
            'cookie_consent_id' => $consent->id,
            'previous_analytics' => true,
            'previous_marketing' => true,
            'previous_preferences' => true,
            'new_analytics' => true,
            'new_marketing' => false,
            'new_preferences' => true,
            'change_type' => 'update',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'change_source' => 'settings',
            'changed_at' => now(),
        ]);

        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->getJson("/api/tenant-client/consent/history?visitor_id={$this->visitorId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'history' => [
                        '*' => [
                            'change_type',
                            'changed_at',
                            'changes',
                            'source',
                        ],
                    ],
                ],
            ]);

        $history = $response->json('data.history');
        $this->assertCount(2, $history);
    }

    /** @test */
    public function it_validates_consent_request_data()
    {
        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent', [
                // Missing required fields
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'errors',
            ]);
    }

    /** @test */
    public function it_validates_action_type()
    {
        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->postJson('/api/tenant-client/consent', [
                'visitor_id' => $this->visitorId,
                'analytics' => true,
                'marketing' => true,
                'preferences' => true,
                'action' => 'invalid_action', // Invalid action
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_isolates_consent_by_tenant()
    {
        $otherTenant = Tenant::factory()->create();
        $otherDomain = TenantDomain::factory()->create([
            'tenant_id' => $otherTenant->id,
            'domain' => 'other.example.com',
        ]);

        // Create consent for original tenant
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now(),
        ]);

        // Query with other tenant should not find it
        $response = $this->withHeaders([
            'X-Tenant-ID' => $otherTenant->id,
            'X-Domain-ID' => $otherDomain->id,
            'Content-Type' => 'application/json',
        ])->getJson("/api/tenant-client/consent?visitor_id={$this->visitorId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_consent' => false,
                ],
            ]);
    }

    /** @test */
    public function it_does_not_return_withdrawn_consent()
    {
        // Create withdrawn consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now()->subDay(),
            'withdrawn_at' => now(),
        ]);

        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->getJson("/api/tenant-client/consent?visitor_id={$this->visitorId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_consent' => false,
                ],
            ]);
    }

    /** @test */
    public function it_does_not_return_expired_consent()
    {
        // Create expired consent
        CookieConsent::create([
            'tenant_id' => $this->tenant->id,
            'visitor_id' => $this->visitorId,
            'necessary' => true,
            'analytics' => true,
            'marketing' => true,
            'preferences' => true,
            'action' => 'accept_all',
            'consent_version' => '1.0',
            'ip_address' => '127.0.0.1',
            'consent_source' => 'banner',
            'consented_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders($this->getTenantClientHeaders())
            ->getJson("/api/tenant-client/consent?visitor_id={$this->visitorId}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_consent' => false,
                ],
            ]);
    }
}
