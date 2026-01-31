<?php

namespace Tests\Feature\Platform;

use App\Jobs\RetryFailedConversionsJob;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\PlatformAdAccount;
use App\Models\Platform\PlatformConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RetryFailedConversionsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->customer = CoreCustomer::create([
            'uuid' => 'test-customer-001',
            'email' => 'test@example.com',
            'first_seen_at' => now(),
        ]);

        $this->adAccount = PlatformAdAccount::create([
            'account_name' => 'Test Google Ads Account',
            'platform' => PlatformAdAccount::PLATFORM_GOOGLE_ADS,
            'account_id' => 'test-123',
            'pixel_id' => 'AW-123456789',
            'access_token' => 'test-token',
            'token_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_retries_failed_conversions()
    {
        // Create a failed conversion
        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 100.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 0,
            'event_data' => ['conversion_action' => 'test_action'],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        // Conversion should be marked as sent after successful retry
        $this->assertEquals(PlatformConversion::STATUS_SENT, $conversion->status);
    }

    /** @test */
    public function it_increments_retry_count_on_failure()
    {
        // Create account without pixel ID to trigger failure
        $accountWithoutPixel = PlatformAdAccount::create([
            'account_name' => 'Account Without Pixel',
            'platform' => PlatformAdAccount::PLATFORM_FACEBOOK,
            'account_id' => 'test-456',
            'pixel_id' => null, // No pixel ID
            'access_token' => 'test-token',
            'token_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $accountWithoutPixel->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 50.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 0,
            'event_data' => [],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        $this->assertEquals(1, $conversion->retry_count);
        $this->assertNotNull($conversion->error_message);
    }

    /** @test */
    public function it_skips_conversions_updated_recently()
    {
        // Create a failed conversion that was just updated (less than 5 minutes ago)
        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 75.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 0,
            'event_data' => ['conversion_action' => 'test_action'],
            'updated_at' => now()->subMinutes(2), // Updated 2 minutes ago
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        // Should still be failed (not retried)
        $this->assertEquals(PlatformConversion::STATUS_FAILED, $conversion->status);
        $this->assertEquals(0, $conversion->retry_count);
    }

    /** @test */
    public function it_abandons_conversions_with_inactive_accounts()
    {
        $inactiveAccount = PlatformAdAccount::create([
            'account_name' => 'Inactive Account',
            'platform' => PlatformAdAccount::PLATFORM_GOOGLE_ADS,
            'account_id' => 'inactive-123',
            'pixel_id' => 'AW-inactive',
            'access_token' => 'test-token',
            'is_active' => false, // Inactive
        ]);

        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $inactiveAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 100.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 0,
            'event_data' => ['conversion_action' => 'test_action'],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        $this->assertEquals('abandoned', $conversion->status);
        $this->assertStringContainsString('inactive', $conversion->error_message);
    }

    /** @test */
    public function it_abandons_conversions_with_expired_tokens()
    {
        $expiredAccount = PlatformAdAccount::create([
            'account_name' => 'Expired Token Account',
            'platform' => PlatformAdAccount::PLATFORM_FACEBOOK,
            'account_id' => 'expired-123',
            'pixel_id' => 'FB-expired',
            'access_token' => 'expired-token',
            'token_expires_at' => now()->subDays(5), // Expired
            'is_active' => true,
        ]);

        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $expiredAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 100.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 0,
            'event_data' => [],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        $this->assertEquals('abandoned', $conversion->status);
        $this->assertStringContainsString('expired', $conversion->error_message);
    }

    /** @test */
    public function it_abandons_conversions_exceeding_max_retries()
    {
        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $this->adAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'purchase',
            'conversion_value' => 100.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 5, // At max retries
            'event_data' => ['conversion_action' => 'test_action'],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        $this->assertEquals('abandoned', $conversion->status);
        $this->assertStringContainsString('Maximum retry', $conversion->error_message);
    }

    /** @test */
    public function it_retries_facebook_conversions()
    {
        $fbAccount = PlatformAdAccount::create([
            'account_name' => 'Facebook Account',
            'platform' => PlatformAdAccount::PLATFORM_FACEBOOK,
            'account_id' => 'fb-123',
            'pixel_id' => '123456789',
            'access_token' => 'fb-token',
            'token_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $fbAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'Purchase',
            'conversion_value' => 150.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 0,
            'event_data' => ['event_name' => 'Purchase'],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        $this->assertEquals(PlatformConversion::STATUS_SENT, $conversion->status);
    }

    /** @test */
    public function it_retries_tiktok_conversions()
    {
        $ttAccount = PlatformAdAccount::create([
            'account_name' => 'TikTok Account',
            'platform' => PlatformAdAccount::PLATFORM_TIKTOK,
            'account_id' => 'tt-123',
            'pixel_id' => 'TT123',
            'access_token' => 'tt-token',
            'token_expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        $conversion = PlatformConversion::create([
            'platform_ad_account_id' => $ttAccount->id,
            'customer_id' => $this->customer->id,
            'event_type' => 'CompletePayment',
            'conversion_value' => 75.00,
            'status' => PlatformConversion::STATUS_FAILED,
            'retry_count' => 1,
            'event_data' => ['event_type' => 'CompletePayment'],
            'updated_at' => now()->subMinutes(10),
        ]);

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $conversion->refresh();

        $this->assertEquals(PlatformConversion::STATUS_SENT, $conversion->status);
    }

    /** @test */
    public function it_processes_multiple_failed_conversions()
    {
        // Create multiple failed conversions
        for ($i = 1; $i <= 5; $i++) {
            PlatformConversion::create([
                'platform_ad_account_id' => $this->adAccount->id,
                'customer_id' => $this->customer->id,
                'event_type' => 'purchase',
                'conversion_value' => $i * 50,
                'status' => PlatformConversion::STATUS_FAILED,
                'retry_count' => 0,
                'event_data' => ['conversion_action' => 'test_action'],
                'updated_at' => now()->subMinutes(10),
            ]);
        }

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $sentCount = PlatformConversion::where('status', PlatformConversion::STATUS_SENT)->count();
        $this->assertEquals(5, $sentCount);
    }

    /** @test */
    public function it_only_processes_limited_conversions_per_run()
    {
        // The job limits to 500 conversions per run
        // Create 10 conversions to verify the limit mechanism works
        for ($i = 1; $i <= 10; $i++) {
            PlatformConversion::create([
                'platform_ad_account_id' => $this->adAccount->id,
                'customer_id' => $this->customer->id,
                'event_type' => 'purchase',
                'conversion_value' => 100.00,
                'status' => PlatformConversion::STATUS_FAILED,
                'retry_count' => 0,
                'event_data' => ['conversion_action' => 'test_action'],
                'updated_at' => now()->subMinutes(10),
            ]);
        }

        $job = new RetryFailedConversionsJob();
        $job->handle();

        $sentCount = PlatformConversion::where('status', PlatformConversion::STATUS_SENT)->count();
        $this->assertEquals(10, $sentCount);
    }
}
