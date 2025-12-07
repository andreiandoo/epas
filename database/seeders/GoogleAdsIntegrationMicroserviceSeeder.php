<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class GoogleAdsIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'google-ads-integration'],
            [
                'name' => 'Google Ads Integration',
                'description' => 'Connect your Google Ads account to automatically track conversions from ticket purchases, leads, and registrations. Optimize your ad campaigns with real conversion data and build custom audiences from your customer base.',
                'category' => 'integrations',
                'icon' => 'chart-bar',
                'price' => 25.00,
                'is_active' => true,
                'is_public' => true,
                'requires_setup' => true,
                'features' => [
                    'Automatic purchase conversion tracking',
                    'Enhanced conversions with hashed customer data',
                    'Support for GCLID, GBRAID, and WBRAID',
                    'Lead and registration conversion tracking',
                    'Custom conversion actions setup',
                    'Batch conversion uploads',
                    'Customer Match audience sync',
                    'Real-time conversion status monitoring',
                    'Value-based bidding support',
                    'Conversion deduplication',
                    'Test mode for validation',
                    'OAuth 2.0 secure authentication',
                ],
                'config_schema' => [
                    'customer_id' => [
                        'type' => 'string',
                        'label' => 'Customer ID',
                        'required' => true,
                        'description' => 'Your Google Ads Customer ID (xxx-xxx-xxxx)',
                        'placeholder' => '123-456-7890',
                    ],
                    'primary_conversion_action' => [
                        'type' => 'select',
                        'label' => 'Primary Conversion Action',
                        'required' => false,
                        'description' => 'Select after connecting your account',
                        'options' => [],
                    ],
                    'track_purchases' => [
                        'type' => 'boolean',
                        'label' => 'Track Purchases',
                        'default' => true,
                        'description' => 'Send purchase conversions when tickets are bought',
                    ],
                    'track_leads' => [
                        'type' => 'boolean',
                        'label' => 'Track Leads',
                        'default' => true,
                        'description' => 'Send lead conversions for registrations',
                    ],
                    'enhanced_conversions' => [
                        'type' => 'boolean',
                        'label' => 'Enhanced Conversions',
                        'default' => true,
                        'description' => 'Include hashed customer data for better attribution',
                    ],
                ],
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/google-ads/auth',
                        'description' => 'Get OAuth authorization URL',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/google-ads/callback',
                        'description' => 'Handle OAuth callback',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/google-ads/connection',
                        'description' => 'Get connection status',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/google-ads/conversions',
                        'description' => 'Send conversion event',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/google-ads/conversion-actions',
                        'description' => 'List conversion actions',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/google-ads/audiences',
                        'description' => 'Create Customer Match audience',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/google-ads/audiences/{id}/sync',
                        'description' => 'Sync users to audience',
                    ],
                ],
                'webhooks' => [],
                'settings' => [
                    'requires_oauth' => true,
                    'oauth_provider' => 'google',
                    'scopes' => ['https://www.googleapis.com/auth/adwords'],
                    'supports_batch' => true,
                    'batch_size' => 2000,
                    'rate_limit' => 10000, // per day
                ],
            ]
        );
    }
}
