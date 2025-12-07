<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class LinkedInAdsIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'linkedin-ads-integration'],
            [
                'name' => 'LinkedIn Ads Integration',
                'description' => 'Connect your LinkedIn Ads account for conversion tracking. Perfect for B2B events, professional conferences, and corporate ticketing. Track purchases, leads, and registrations with hashed user data for privacy-compliant attribution.',
                'category' => 'integrations',
                'icon' => 'briefcase',
                'price' => 25.00,
                'is_active' => true,
                'is_public' => true,
                'requires_setup' => true,
                'features' => [
                    'Server-side conversion tracking via Conversions API',
                    'Purchase conversion tracking with value',
                    'Lead generation conversion tracking',
                    'Sign-up and registration events',
                    'Support for LinkedIn first-party cookie (li_fat_id)',
                    'SHA-256 hashed email matching',
                    'LinkedIn Member ID matching',
                    'Custom conversion rules setup',
                    'Batch conversion uploads',
                    'Matched Audiences (Customer Match)',
                    'OAuth 2.0 secure authentication',
                    'View-through attribution support',
                    'Multi-touch attribution reporting',
                ],
                'config_schema' => [
                    'ad_account_id' => [
                        'type' => 'string',
                        'label' => 'Ad Account ID',
                        'required' => true,
                        'description' => 'Your LinkedIn Ad Account ID',
                    ],
                    'primary_conversion_rule' => [
                        'type' => 'select',
                        'label' => 'Primary Conversion Rule',
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
                    'track_signups' => [
                        'type' => 'boolean',
                        'label' => 'Track Sign-ups',
                        'default' => true,
                        'description' => 'Send sign-up conversions for account creations',
                    ],
                ],
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/linkedin-ads/auth',
                        'description' => 'Get OAuth authorization URL',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/linkedin-ads/callback',
                        'description' => 'Handle OAuth callback',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/linkedin-ads/connection',
                        'description' => 'Get connection status',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/linkedin-ads/conversions',
                        'description' => 'Send conversion event',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/integrations/linkedin-ads/conversion-rules',
                        'description' => 'List conversion rules',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/linkedin-ads/audiences',
                        'description' => 'Create Matched Audience',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/integrations/linkedin-ads/audiences/{id}/sync',
                        'description' => 'Sync users to audience',
                    ],
                ],
                'webhooks' => [],
                'settings' => [
                    'requires_oauth' => true,
                    'oauth_provider' => 'linkedin',
                    'scopes' => ['r_ads', 'rw_ads', 'r_ads_reporting', 'r_organization_social'],
                    'supports_batch' => true,
                    'batch_size' => 10000,
                    'api_version' => '202401',
                ],
            ]
        );
    }
}
