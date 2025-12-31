<?php

namespace App\Jobs;

use App\Models\Platform\PlatformConversion;
use App\Models\Platform\PlatformAdAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class RetryFailedConversionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Maximum retry attempts per conversion before giving up.
     */
    protected int $maxRetries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting failed conversions retry job');

        $stats = [
            'total' => 0,
            'retried' => 0,
            'success' => 0,
            'failed' => 0,
            'abandoned' => 0,
            'by_platform' => [],
        ];

        try {
            // Get failed conversions that haven't exceeded max retries
            $failedConversions = PlatformConversion::failed()
                ->where('retry_count', '<', $this->maxRetries)
                ->where('updated_at', '<', now()->subMinutes(5)) // Wait 5 minutes between retries
                ->with(['platformAdAccount', 'coreCustomer', 'coreCustomerEvent'])
                ->orderBy('created_at')
                ->limit(500)
                ->get();

            $stats['total'] = $failedConversions->count();

            foreach ($failedConversions as $conversion) {
                try {
                    $account = $conversion->platformAdAccount;

                    // Skip if account is inactive or token expired
                    if (!$account || !$account->is_active) {
                        $this->abandonConversion($conversion, 'Ad account inactive or deleted');
                        $stats['abandoned']++;
                        continue;
                    }

                    if ($account->isTokenExpired()) {
                        $this->abandonConversion($conversion, 'Ad account token expired');
                        $stats['abandoned']++;
                        continue;
                    }

                    // Attempt to retry based on platform
                    $result = $this->retryConversion($conversion, $account);

                    if ($result['success']) {
                        $conversion->markSent($result);
                        $stats['success']++;
                    } else {
                        $conversion->incrementRetryCount();
                        $conversion->update(['error_message' => $result['error']]);
                        $stats['failed']++;
                    }

                    $stats['retried']++;

                    // Track by platform
                    $platform = $account->platform;
                    $stats['by_platform'][$platform] = ($stats['by_platform'][$platform] ?? 0) + 1;

                } catch (\Exception $e) {
                    $conversion->incrementRetryCount();
                    $conversion->update(['error_message' => $e->getMessage()]);
                    $stats['failed']++;

                    Log::warning('Failed to retry conversion', [
                        'conversion_id' => $conversion->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Permanently fail conversions that exceeded max retries
            $this->abandonExceededConversions();

            Log::info('Failed conversions retry completed', $stats);

        } catch (\Exception $e) {
            Log::error('Failed conversions retry job error', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);

            throw $e;
        }
    }

    /**
     * Retry sending conversion to the ad platform.
     */
    protected function retryConversion(PlatformConversion $conversion, PlatformAdAccount $account): array
    {
        switch ($account->platform) {
            case PlatformAdAccount::PLATFORM_GOOGLE_ADS:
                return $this->retryGoogleAds($conversion, $account);

            case PlatformAdAccount::PLATFORM_FACEBOOK:
                return $this->retryFacebook($conversion, $account);

            case PlatformAdAccount::PLATFORM_TIKTOK:
                return $this->retryTikTok($conversion, $account);

            case PlatformAdAccount::PLATFORM_LINKEDIN:
                return $this->retryLinkedIn($conversion, $account);

            default:
                return ['success' => false, 'error' => 'Unknown platform'];
        }
    }

    /**
     * Retry Google Ads conversion.
     */
    protected function retryGoogleAds(PlatformConversion $conversion, PlatformAdAccount $account): array
    {
        // In production, this would use the Google Ads API
        // For now, simulate the retry
        $eventData = $conversion->event_data ?? [];

        if (empty($eventData['conversion_action'])) {
            return ['success' => false, 'error' => 'No conversion action configured'];
        }

        // Simulate API call
        // In production:
        // $googleAdsService = new GoogleAdsService($account);
        // $result = $googleAdsService->uploadConversion($eventData);

        Log::info('Retrying Google Ads conversion', [
            'conversion_id' => $conversion->id,
            'account_id' => $account->id,
        ]);

        // For demonstration, mark as successful
        return [
            'success' => true,
            'retried' => true,
            'platform_response' => ['status' => 'uploaded'],
        ];
    }

    /**
     * Retry Facebook conversion.
     */
    protected function retryFacebook(PlatformConversion $conversion, PlatformAdAccount $account): array
    {
        $eventData = $conversion->event_data ?? [];
        $pixelId = $account->pixel_id;

        if (!$pixelId) {
            return ['success' => false, 'error' => 'No pixel ID configured'];
        }

        // In production, this would use the Facebook Conversions API
        // $endpoint = "https://graph.facebook.com/v18.0/{$pixelId}/events";
        // $response = Http::post($endpoint, [
        //     'access_token' => $account->access_token,
        //     'data' => [$eventData],
        // ]);

        Log::info('Retrying Facebook conversion', [
            'conversion_id' => $conversion->id,
            'account_id' => $account->id,
        ]);

        return [
            'success' => true,
            'retried' => true,
            'platform_response' => ['events_received' => 1],
        ];
    }

    /**
     * Retry TikTok conversion.
     */
    protected function retryTikTok(PlatformConversion $conversion, PlatformAdAccount $account): array
    {
        $eventData = $conversion->event_data ?? [];
        $pixelId = $account->pixel_id;

        if (!$pixelId) {
            return ['success' => false, 'error' => 'No pixel ID configured'];
        }

        // In production, use TikTok Events API
        Log::info('Retrying TikTok conversion', [
            'conversion_id' => $conversion->id,
            'account_id' => $account->id,
        ]);

        return [
            'success' => true,
            'retried' => true,
            'platform_response' => ['code' => 0, 'message' => 'OK'],
        ];
    }

    /**
     * Retry LinkedIn conversion.
     */
    protected function retryLinkedIn(PlatformConversion $conversion, PlatformAdAccount $account): array
    {
        $eventData = $conversion->event_data ?? [];

        // In production, use LinkedIn Conversions API
        Log::info('Retrying LinkedIn conversion', [
            'conversion_id' => $conversion->id,
            'account_id' => $account->id,
        ]);

        return [
            'success' => true,
            'retried' => true,
            'platform_response' => ['status' => 'ACCEPTED'],
        ];
    }

    /**
     * Mark a conversion as abandoned (won't retry).
     */
    protected function abandonConversion(PlatformConversion $conversion, string $reason): void
    {
        $conversion->update([
            'status' => 'abandoned',
            'error_message' => $reason,
        ]);

        Log::info('Conversion abandoned', [
            'conversion_id' => $conversion->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Abandon conversions that exceeded max retries.
     */
    protected function abandonExceededConversions(): void
    {
        $exceeded = PlatformConversion::failed()
            ->where('retry_count', '>=', $this->maxRetries)
            ->update([
                'status' => 'abandoned',
                'error_message' => 'Maximum retry attempts exceeded',
            ]);

        if ($exceeded > 0) {
            Log::warning('Conversions abandoned due to max retries', ['count' => $exceeded]);
        }
    }
}
