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
                'name' => 'Tracking & Pixels Manager',
                'description' => 'Manage tracking pixels and analytics integrations with GDPR-compliant consent management. Support for Google Analytics 4, Google Tag Manager, Meta Pixel, and TikTok Pixel.',
                'long_description' => 'Complete tracking and pixels management solution for your website. Easily integrate analytics and marketing pixels while staying GDPR compliant.

**Features:**
- Google Analytics 4 (GA4) integration
- Google Tag Manager (GTM) integration
- Meta Pixel (Facebook) integration
- TikTok Pixel integration
- GDPR-compliant consent management
- Standard ecommerce event tracking
- CSP-compliant script injection
- Page scope control (public/admin/all)
- Injection location control (head/body)
- Debug preview for testing

**Privacy & Compliance:**
- No tracking without explicit user consent
- Automatic script blocking based on consent
- Consent categories: Analytics & Marketing
- Session-based consent storage

**Ecommerce Events:**
- Page View
- View Item
- Add to Cart
- Begin Checkout
- Purchase

Perfect for e-commerce sites, event platforms, and any business that needs comprehensive analytics while respecting user privacy.',
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
                    'Valid tracking IDs from providers',
                    'HTTPS website (required for consent)',
                    'User consent banner (recommended)',
                ],
                'icon' => 'heroicon-o-chart-bar',
                'category' => 'marketing',
                'documentation_url' => null,
            ]
        );
    }
}
