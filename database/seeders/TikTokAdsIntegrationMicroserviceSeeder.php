<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class TikTokAdsIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'tiktok-ads-integration'],
            [
                'name' => 'TikTok Ads Integration',
                'description' => 'Connect your TikTok Ads account to track conversions from ticket purchases. Send server-side events via the TikTok Events API for accurate attribution and create custom audiences from your customer base.',
                'category' => 'integrations',
                'icon' => 'video-camera',
                'price' => 20.00,
                'is_active' => true,
                'is_public' => true,
                'requires_setup' => true,
                'features' => [
                    'Server-side conversion tracking via Events API',
                    'CompletePayment event for purchases',
                    'AddToCart and InitiateCheckout tracking',
                    'CompleteRegistration for signups',
                    'ViewContent for page views',
                    'Support for TikTok Click ID (ttclid)',
                    'TikTok cookie (_ttp) tracking',
                    'Batch event uploads',
                    'Custom audience creation and sync',
                    'Event deduplication',
                    'Test mode for validation',
                    'Hashed user data for privacy',
                ],
                'config_schema' => [
                    'pixel_id' => [
                        'type' => 'string',
                        'label' => 'Pixel ID',
                        'required' => true,
                        'description' => 'Your TikTok Pixel ID',
                    ],
                    'access_token' => [
                        'type' => 'secret',
                        'label' => 'Access Token',
                        'required' => true,
                        'description' => 'TikTok Events API Access Token',
                    ],
                    'advertiser_id' => [
                        'type' => 'string',
                        'label' => 'Advertiser ID',
                        'required' => false,
                        'description' => 'Required for custom audiences',
                    ],
                    'test_mode' => [
                        'type' => 'boolean',
                        'label' => 'Test Mode',
                        'default' => false,
                        'description' => 'Send events to Test Events in TikTok Events Manager',
                    ],
                    'track_purchases' => [
                        'type' => 'boolean',
                        'label' => 'Track Purchases',
                        'default' => true,
                    ],
                    'track_add_to_cart' => [
                        'type' => 'boolean',
                        'label' => 'Track Add to Cart',
                        'default' => true,
                    ],
                    'track_registrations' => [
                        'type' => 'boolean',
                        'label' => 'Track Registrations',
                        'default' => true,
                    ],
                ],
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/tiktok-ads/connection',
                        'description' => 'Get connection status',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/tiktok-ads/connect',
                        'description' => 'Create new connection',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/tiktok-ads/events',
                        'description' => 'Send event to TikTok',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/tiktok-ads/events/batch',
                        'description' => 'Send batch of events',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/tiktok-ads/audiences',
                        'description' => 'Create custom audience',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/tiktok-ads/audiences/{id}/sync',
                        'description' => 'Sync users to audience',
                    ],
                ],
                'webhooks' => [],
                'settings' => [
                    'api_version' => 'v1.3',
                    'supports_batch' => true,
                    'batch_size' => 1000,
                    'rate_limit' => 50000, // events per day
                ],
            ]
        );
    }
}
