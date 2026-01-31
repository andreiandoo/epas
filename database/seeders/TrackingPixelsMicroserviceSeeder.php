<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class TrackingPixelsMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'tracking-pixels-manager'],
            [
                'name' => ['en' => 'Tracking & Pixels Manager'],
                'description' => ['en' => 'Manage tracking pixels and analytics integrations with GDPR-compliant consent management. Support for Google Analytics 4, Google Tag Manager, Meta Pixel, and TikTok Pixel.'],
                'pricing_model' => 'recurring',
                'price' => 1.00, // 1 EUR per month
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'features' => [
                    'GA4 Integration',
                    'GTM Integration',
                    'Meta Pixel Integration',
                    'TikTok Pixel Integration',
                    'GDPR Consent Management',
                    'Ecommerce Event Tracking',
                    'Debug & Preview Tools',
                    'Unlimited Pageviews',
                    'CSP-Compliant Scripts',
                ],
                'icon' => 'heroicon-o-chart-bar',
                'category' => 'marketing',
                'documentation_url' => null,
            ]
        );
    }
}
