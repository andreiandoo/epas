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
                'name' => [
                    'en' => 'Facebook Conversions API',
                    'ro' => 'Facebook Conversions API',
                ],
                'description' => [
                    'en' => 'Server-side event tracking for Facebook/Meta Ads. Send conversion events (purchases, leads, registrations) directly to Facebook for improved attribution and ad optimization. Bypasses ad-blockers, iOS 14.5+ ATT and browser privacy restrictions for near-100% data capture.',
                    'ro' => 'Tracking server-side al evenimentelor pentru Facebook/Meta Ads. Trimite evenimente de conversie (achiziții, lead-uri, înregistrări) direct către Facebook pentru atribuire îmbunătățită și optimizare reclame. Trece de adblockere, iOS 14.5+ ATT și restricțiile de privacy ale browserelor pentru capturarea aproape 100% a datelor.',
                ],
                'short_description' => [
                    'en' => 'Server-side conversion tracking for Meta Ads with browser/CAPI deduplication.',
                    'ro' => 'Tracking server-side al conversiilor pentru Meta Ads cu deduplicare browser/CAPI.',
                ],
                'category' => 'marketing',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'features' => [
                    'en' => [
                        'Purchase Events',
                        'Lead Events',
                        'Registration Events',
                        'Custom Events',
                        'Event Deduplication',
                        'User Data Hashing (SHA-256)',
                        'Test Events',
                        'Custom Audiences',
                    ],
                    'ro' => [
                        'Evenimente Achiziție',
                        'Evenimente Lead',
                        'Evenimente Înregistrare',
                        'Evenimente Personalizate',
                        'Deduplicare Evenimente',
                        'Hashing Date Utilizatori (SHA-256)',
                        'Evenimente Test',
                        'Audiențe Personalizate',
                    ],
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
