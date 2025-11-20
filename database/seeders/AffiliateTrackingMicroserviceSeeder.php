<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class AffiliateTrackingMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'affiliate-tracking'],
            [
                'name' => 'Affiliate Tracking & Commissions',
                'description' => 'Complete affiliate tracking system with last-click attribution, commission management, and detailed reporting. Track affiliate clicks, manage multiple affiliates, set custom commission rates, and monitor conversions with 90-day cookie tracking. Includes deduplication, self-purchase guard, and comprehensive dashboards for both tenants and affiliates.',
                'icon' => 'heroicon-o-user-group',
                'price' => 10.00,
                'currency' => 'EUR',
                'billing_cycle' => 'one_time',
                'pricing_model' => 'one_time',
                'category' => 'marketing',
                'is_active' => true,
                'features' => [
                    'Last-click attribution with 90-day cookie window',
                    'Support for both link and coupon-based tracking',
                    'Configurable commission rates (percentage or fixed)',
                    'Automatic deduplication of conversions',
                    'Self-purchase guard to prevent fraud',
                    'Detailed analytics and reporting dashboards',
                    'CSV export for accounting',
                    'Separate dashboards for tenants and affiliates',
                    'Real-time click and conversion tracking',
                    'Conversion status management (pending/approved/reversed)',
                ],
            ]
        );
    }
}
