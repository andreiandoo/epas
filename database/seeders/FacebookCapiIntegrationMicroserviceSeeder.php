<?php

namespace Database\Seeders;

use App\Models\Microservice;
use App\Models\MicroserviceFeature;
use Illuminate\Database\Seeder;

class FacebookCapiIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        $microservice = Microservice::updateOrCreate(
            ['slug' => 'facebook-capi-integration'],
            [
                'name' => 'Facebook Conversions API',
                'description' => 'Server-side event tracking for Facebook/Meta Ads. Send conversion events (purchases, leads, registrations) directly to Facebook for improved attribution and ad optimization.',
                'category' => 'marketing',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
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

        $features = [
            [
                'name' => 'Purchase Events',
                'slug' => 'purchase-events',
                'description' => 'Track order completions as Purchase conversions',
            ],
            [
                'name' => 'Lead Events',
                'slug' => 'lead-events',
                'description' => 'Track form submissions and registrations as Leads',
            ],
            [
                'name' => 'Registration Events',
                'slug' => 'registration-events',
                'description' => 'Track ticket purchases as CompleteRegistration',
            ],
            [
                'name' => 'Custom Events',
                'slug' => 'custom-events',
                'description' => 'Send custom conversion events',
            ],
            [
                'name' => 'Event Deduplication',
                'slug' => 'event-deduplication',
                'description' => 'Deduplicate with browser pixel events',
            ],
            [
                'name' => 'User Data Hashing',
                'slug' => 'user-data-hashing',
                'description' => 'Automatic SHA-256 hashing of PII',
            ],
            [
                'name' => 'Test Events',
                'slug' => 'test-events',
                'description' => 'Test mode for validation in Events Manager',
            ],
            [
                'name' => 'Custom Audiences',
                'slug' => 'custom-audiences',
                'description' => 'Sync customer lists to Facebook Custom Audiences',
            ],
        ];

        foreach ($features as $feature) {
            MicroserviceFeature::updateOrCreate(
                ['microservice_id' => $microservice->id, 'slug' => $feature['slug']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
