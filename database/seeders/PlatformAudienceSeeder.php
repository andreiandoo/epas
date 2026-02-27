<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformAudience;
use Illuminate\Database\Seeder;

class PlatformAudienceSeeder extends Seeder
{
    public function run(): void
    {
        $audiences = [
            // ===== STANDARD AUDIENCES =====
            [
                'name' => 'All Customers',
                'description' => 'All customers with email or phone data — full customer base for broad campaigns.',
                'audience_type' => PlatformAudience::TYPE_ALL_CUSTOMERS,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_DAILY,
            ],
            [
                'name' => 'All Purchasers',
                'description' => 'Customers who have completed at least one purchase.',
                'audience_type' => PlatformAudience::TYPE_PURCHASERS,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_DAILY,
            ],
            [
                'name' => 'High-Value Customers',
                'description' => 'Top spenders: total spent >= 500 or RFM score >= 12.',
                'audience_type' => PlatformAudience::TYPE_HIGH_VALUE,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
            ],
            [
                'name' => 'Recent Visitors (30 days)',
                'description' => 'Customers active in the last 30 days — warm audience for retargeting.',
                'audience_type' => PlatformAudience::TYPE_RECENT_VISITORS,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_DAILY,
            ],
            [
                'name' => 'Cart Abandoners',
                'description' => 'Customers who abandoned their cart in the last 30 days — high-intent retargeting.',
                'audience_type' => PlatformAudience::TYPE_CART_ABANDONERS,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_DAILY,
            ],
            [
                'name' => 'Engaged Users',
                'description' => 'High engagement: score >= 50 or 10+ pageviews.',
                'audience_type' => PlatformAudience::TYPE_ENGAGED_USERS,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
            ],
            [
                'name' => 'Inactive Customers (90+ days)',
                'description' => 'Customers not seen in 90+ days — win-back campaigns.',
                'audience_type' => PlatformAudience::TYPE_INACTIVE,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
            ],

            // ===== CUSTOM AUDIENCES =====
            [
                'name' => 'VIP Customers',
                'description' => 'Customers in the VIP segment — top tier for exclusive offers.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'customer_segment', 'operator' => '=', 'value' => 'VIP'],
                ],
            ],
            [
                'name' => 'First-Time Buyers',
                'description' => 'Customers with exactly one purchase — nurture into repeat buyers.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'total_orders', 'operator' => '=', 'value' => 1],
                ],
            ],
            [
                'name' => 'Repeat Buyers',
                'description' => 'Customers with 2+ purchases — loyal customers for upsell.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'total_orders', 'operator' => '>=', 'value' => 2],
                ],
            ],
            [
                'name' => 'RFM Champions',
                'description' => 'Best customers by RFM analysis — bought recently, buy often, spend the most.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'rfm_segment', 'operator' => '=', 'value' => 'Champions'],
                ],
            ],
            [
                'name' => 'At Risk Customers',
                'description' => 'RFM "At Risk" segment — used to be good customers, slipping away.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'rfm_segment', 'operator' => 'in', 'value' => ['At Risk', 'Cannot Lose Them']],
                ],
            ],
            [
                'name' => 'Email Subscribers',
                'description' => 'Customers who opted in to marketing emails.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_DAILY,
                'segment_rules' => [
                    ['field' => 'email_subscribed', 'operator' => '=', 'value' => true],
                    ['field' => 'marketing_consent', 'operator' => '=', 'value' => true],
                ],
            ],
            [
                'name' => 'Google Ads Converters',
                'description' => 'Customers acquired via Google Ads — for lookalike and exclusion lists.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'first_gclid', 'operator' => 'is_not_null'],
                    ['field' => 'total_orders', 'operator' => '>', 'value' => 0],
                ],
            ],
            [
                'name' => 'Facebook Converters',
                'description' => 'Customers acquired via Facebook/Instagram Ads.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'first_fbclid', 'operator' => 'is_not_null'],
                    ['field' => 'total_orders', 'operator' => '>', 'value' => 0],
                ],
            ],
            [
                'name' => 'Romanian Customers',
                'description' => 'Customers from Romania — geo-targeted campaigns.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'country_code', 'operator' => '=', 'value' => 'RO'],
                ],
            ],
            [
                'name' => 'Multi-Event Attendees',
                'description' => 'Customers who attended 3+ events — event enthusiasts.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'total_events_attended', 'operator' => '>=', 'value' => 3],
                ],
            ],
            [
                'name' => 'Recent Purchasers (7 days)',
                'description' => 'Customers who purchased in the last 7 days — fresh buyers for cross-sell.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_DAILY,
                'segment_rules' => [
                    ['field' => 'last_purchase_at', 'operator' => 'days_ago', 'value' => 7],
                ],
            ],
            [
                'name' => 'Lapsed VIPs',
                'description' => 'Former VIP customers who haven\'t purchased recently — high-value win-back.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'customer_segment', 'operator' => '=', 'value' => 'Lapsed VIP'],
                ],
            ],
            [
                'name' => 'Engaged Non-Buyers',
                'description' => 'Visitors with high engagement but no purchases — conversion targets.',
                'audience_type' => PlatformAudience::TYPE_CUSTOM,
                'status' => PlatformAudience::STATUS_ACTIVE,
                'is_auto_sync' => true,
                'sync_frequency' => PlatformAudience::SYNC_WEEKLY,
                'segment_rules' => [
                    ['field' => 'customer_segment', 'operator' => '=', 'value' => 'Engaged Non-Buyer'],
                ],
            ],

            // ===== LOOKALIKE AUDIENCES =====
            [
                'name' => 'Purchasers Lookalike 1% (RO)',
                'description' => 'Lookalike audience based on all purchasers — 1% most similar in Romania.',
                'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
                'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_PURCHASERS,
                'lookalike_percentage' => 1,
                'lookalike_country' => 'RO',
                'status' => PlatformAudience::STATUS_DRAFT,
                'is_auto_sync' => false,
            ],
            [
                'name' => 'High-Value Lookalike 1% (RO)',
                'description' => 'Lookalike audience based on high-value customers (RFM 12+).',
                'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
                'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_HIGH_VALUE,
                'lookalike_percentage' => 1,
                'lookalike_country' => 'RO',
                'status' => PlatformAudience::STATUS_DRAFT,
                'is_auto_sync' => false,
            ],
            [
                'name' => 'Top Spenders Lookalike 3% (RO)',
                'description' => 'Lookalike based on top 10% spenders — broader reach.',
                'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
                'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_TOP_SPENDERS,
                'lookalike_percentage' => 3,
                'lookalike_country' => 'RO',
                'status' => PlatformAudience::STATUS_DRAFT,
                'is_auto_sync' => false,
            ],
            [
                'name' => 'Engaged Lookalike 2% (RO)',
                'description' => 'Lookalike based on highly engaged users (50+ engagement score).',
                'audience_type' => PlatformAudience::TYPE_LOOKALIKE,
                'lookalike_source_type' => PlatformAudience::LOOKALIKE_SOURCE_ENGAGED,
                'lookalike_percentage' => 2,
                'lookalike_country' => 'RO',
                'status' => PlatformAudience::STATUS_DRAFT,
                'is_auto_sync' => false,
            ],
        ];

        foreach ($audiences as $audienceData) {
            PlatformAudience::updateOrCreate(
                [
                    'name' => $audienceData['name'],
                ],
                $audienceData
            );
        }
    }
}
