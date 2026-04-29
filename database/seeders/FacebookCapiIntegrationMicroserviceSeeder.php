<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class FacebookCapiIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'facebook-capi-integration'],
            [
                'name' => 'Facebook Conversions API',
                'description' => 'Server-side event tracking for Facebook/Meta Ads. Send conversion events (purchases, leads, registrations) directly to Facebook for improved attribution and ad optimization.',
                'category' => 'marketing',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'features' => [
                    'Purchase Events',
                    'Lead Events',
                    'Registration Events',
                    'Custom Events',
                    'Event Deduplication',
                    'User Data Hashing (SHA-256)',
                    'Test Events',
                    'Custom Audiences',
                ],
                'config_schema' => [
                    'pixel_id' => [
                        'type' => 'string',
                        'label' => 'Pixel ID',
                        'required' => true,
                        'description' => 'Your Facebook Pixel ID',
                    ],
                    'access_token' => [
                        'type' => 'secret',
                        'label' => 'Access Token',
                        'required' => true,
                        'description' => 'System User Access Token with ads_management permission',
                    ],
                    'test_mode' => [
                        'type' => 'boolean',
                        'label' => 'Test Mode',
                        'required' => false,
                        'default' => false,
                        'description' => 'Send events to Test Events in Events Manager',
                    ],
                ],
                'required_env_vars' => [],
                'dependencies' => [],
                'documentation_url' => 'https://developers.facebook.com/docs/marketing-api/conversions-api',
            ]
        );
    }
}
