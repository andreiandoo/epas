<?php

namespace Database\Seeders;

use App\Models\CustomerAudienceSegment;
use Illuminate\Database\Seeder;

class CustomerAudienceSegmentsSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            [
                'slug' => CustomerAudienceSegment::SLUG_RECENT_BUYER,
                'name' => 'Recent buyers (30 days)',
                'description' => 'Customers who purchased at least once in the last 30 days. Useful for upsell + lookalike audiences.',
                'criteria' => [
                    'type' => 'orders',
                    'has_paid_order_within_days' => 30,
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => CustomerAudienceSegment::SLUG_HIGH_LTV,
                'name' => 'High LTV (top 10%)',
                'description' => 'Top 10% of customers by lifetime value with at least 1 paid order. Best lookalike seed.',
                'criteria' => [
                    'type' => 'ltv_percentile',
                    'percentile_top' => 10,
                    'min_orders' => 1,
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => CustomerAudienceSegment::SLUG_REPEAT_BUYER,
                'name' => 'Repeat buyers (≥2 orders)',
                'description' => 'Customers with 2 or more paid orders. Highly engaged audience.',
                'criteria' => [
                    'type' => 'orders',
                    'min_paid_orders' => 2,
                ],
                'sort_order' => 3,
            ],
            [
                'slug' => CustomerAudienceSegment::SLUG_ABANDONED_CART,
                'name' => 'Abandoned cart (14 days)',
                'description' => 'Visitors who fired AddToCart or InitiateCheckout in the last 14 days but did not purchase. Prime retargeting candidates.',
                'criteria' => [
                    'type' => 'events',
                    'event_types' => ['add_to_cart', 'begin_checkout'],
                    'within_days' => 14,
                    'exclude_purchasers' => true,
                ],
                'sort_order' => 4,
            ],
            [
                'slug' => CustomerAudienceSegment::SLUG_DORMANT,
                'name' => 'Dormant customers (180 days)',
                'description' => 'Customers whose last purchase was more than 180 days ago. Win-back campaign targets.',
                'criteria' => [
                    'type' => 'orders',
                    'last_purchase_days_ago_min' => 180,
                ],
                'sort_order' => 5,
            ],
        ];

        foreach ($segments as $segment) {
            CustomerAudienceSegment::updateOrCreate(
                ['slug' => $segment['slug']],
                array_merge($segment, ['is_active' => true])
            );
        }
    }
}
