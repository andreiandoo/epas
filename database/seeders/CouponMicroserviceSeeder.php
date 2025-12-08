<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert Coupon Codes microservice metadata
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'coupon-codes'],
            [
                'name' => json_encode(['en' => 'Coupon Codes', 'ro' => 'Coduri Cupon']),
                'description' => json_encode([
                    'en' => 'Complete coupon and promotional code management system. Create campaigns, generate unique codes, validate discounts, track redemptions, and analyze performance. Supports percentage, fixed amount, free shipping, and buy-X-get-Y discounts with advanced targeting options.',
                    'ro' => 'Sistem complet de gestionare cupoane și coduri promoționale. Creează campanii, generează coduri unice, validează reduceri, urmărește utilizări și analizează performanța. Suportă reduceri procentuale, sumă fixă, livrare gratuită și cumpără-X-primește-Y cu opțiuni avansate de targetare.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Create and manage promotional coupon campaigns',
                    'ro' => 'Creează și gestionează campanii de cupoane promoționale',
                ]),
                'price' => 20.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Campaign management with scheduling',
                        'Bulk code generation (thousands of codes)',
                        'Custom code formats (alphanumeric, numeric, custom)',
                        'Code prefixes and suffixes',
                        'Percentage and fixed amount discounts',
                        'Free shipping promotions',
                        'Buy-X-get-Y offers',
                        'Minimum purchase requirements',
                        'Maximum discount caps',
                        'Per-user usage limits',
                        'Total usage limits',
                        'First purchase only option',
                        'Product/category targeting',
                        'Product/category exclusions',
                        'Combinable discount rules',
                        'Real-time code validation',
                        'Redemption tracking',
                        'Validation attempt logging',
                        'Campaign analytics and reporting',
                        'Code export (CSV/JSON)',
                        'Assign codes to specific users',
                        'Redemption reversal support',
                    ],
                    'ro' => [
                        'Gestionare campanii cu programare',
                        'Generare în masă (mii de coduri)',
                        'Formate cod personalizate (alfanumeric, numeric, custom)',
                        'Prefixe și sufixe coduri',
                        'Reduceri procentuale și sumă fixă',
                        'Promoții livrare gratuită',
                        'Oferte cumpără-X-primește-Y',
                        'Cerințe minim achiziție',
                        'Plafonare maximă reducere',
                        'Limite utilizare per utilizator',
                        'Limite utilizare totale',
                        'Opțiune doar prima achiziție',
                        'Targetare produse/categorii',
                        'Excluderi produse/categorii',
                        'Reguli reduceri combinabile',
                        'Validare cod în timp real',
                        'Urmărire utilizări',
                        'Jurnal încercări validare',
                        'Analiză și raportare campanii',
                        'Export coduri (CSV/JSON)',
                        'Atribuire coduri utilizatorilor',
                        'Suport reversare utilizare',
                    ],
                ]),
                'category' => 'sales',
                'status' => 'active',
                'metadata' => json_encode([
                    'endpoints' => [
                        'GET /api/coupons/campaigns',
                        'POST /api/coupons/campaigns',
                        'GET /api/coupons/campaigns/{id}',
                        'PUT /api/coupons/campaigns/{id}',
                        'DELETE /api/coupons/campaigns/{id}',
                        'POST /api/coupons/campaigns/{id}/activate',
                        'POST /api/coupons/campaigns/{id}/generate',
                        'GET /api/coupons/campaigns/{id}/codes',
                        'POST /api/coupons/validate',
                        'POST /api/coupons/redeem',
                        'POST /api/coupons/reverse',
                        'GET /api/coupons/campaigns/{id}/stats',
                        'GET /api/coupons/redemptions',
                        'GET /api/coupons/export/{campaignId}',
                    ],
                    'database_tables' => [
                        'coupon_campaigns',
                        'coupon_codes',
                        'coupon_redemptions',
                        'coupon_generation_jobs',
                        'coupon_validation_attempts',
                    ],
                    'discount_types' => ['percentage', 'fixed', 'free_shipping', 'buy_x_get_y'],
                    'code_formats' => ['alphanumeric', 'numeric', 'alphabetic', 'custom'],
                    'max_codes_per_batch' => 10000,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Create demo tenant data
        $tenantId = 1; // Demo tenant

        // Seed demo campaigns
        $campaigns = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Welcome Discount',
                'description' => 'Welcome discount for new customers',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'minimum_purchase' => 50.00,
                'maximum_discount' => 100.00,
                'applies_to' => 'all',
                'code_format' => 'alphanumeric',
                'code_prefix' => 'WELCOME',
                'code_length' => 6,
                'max_uses_total' => 1,
                'max_uses_per_user' => 1,
                'is_first_purchase_only' => true,
                'is_combinable' => false,
                'status' => 'active',
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addDays(60),
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Summer Sale 2025',
                'description' => 'Summer promotional campaign',
                'discount_type' => 'percentage',
                'discount_value' => 20.00,
                'minimum_purchase' => 100.00,
                'maximum_discount' => 200.00,
                'applies_to' => 'all',
                'code_format' => 'alphanumeric',
                'code_prefix' => 'SUMMER',
                'code_length' => 8,
                'max_uses_total' => null,
                'max_uses_per_user' => 3,
                'is_first_purchase_only' => false,
                'is_combinable' => false,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Free Shipping',
                'description' => 'Free shipping on orders over 75 EUR',
                'discount_type' => 'free_shipping',
                'discount_value' => 0,
                'minimum_purchase' => 75.00,
                'maximum_discount' => null,
                'applies_to' => 'all',
                'code_format' => 'alphabetic',
                'code_prefix' => 'SHIP',
                'code_length' => 4,
                'max_uses_total' => null,
                'max_uses_per_user' => 5,
                'is_first_purchase_only' => false,
                'is_combinable' => true,
                'status' => 'active',
                'starts_at' => null,
                'expires_at' => null,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'VIP Exclusive',
                'description' => 'Exclusive codes for VIP customers',
                'discount_type' => 'fixed',
                'discount_value' => 25.00,
                'minimum_purchase' => 100.00,
                'maximum_discount' => null,
                'applies_to' => 'all',
                'code_format' => 'alphanumeric',
                'code_prefix' => 'VIP',
                'code_suffix' => '25',
                'code_length' => 6,
                'max_uses_total' => 1,
                'max_uses_per_user' => 1,
                'is_first_purchase_only' => false,
                'is_combinable' => false,
                'status' => 'draft',
                'starts_at' => null,
                'expires_at' => null,
            ],
        ];

        $campaignIds = [];
        foreach ($campaigns as $campaign) {
            DB::table('coupon_campaigns')->updateOrInsert(
                ['tenant_id' => $campaign['tenant_id'], 'name' => $campaign['name']],
                array_merge($campaign, ['created_at' => now(), 'updated_at' => now()])
            );

            $campaignIds[$campaign['name']] = DB::table('coupon_campaigns')
                ->where('tenant_id', $tenantId)
                ->where('name', $campaign['name'])
                ->value('id');
        }

        // Seed demo codes for the first campaign
        $demoCodes = [
            ['code' => 'WELCOME123ABC', 'status' => 'active', 'uses_remaining' => 1],
            ['code' => 'WELCOME456DEF', 'status' => 'active', 'uses_remaining' => 1],
            ['code' => 'WELCOME789GHI', 'status' => 'used', 'uses_remaining' => 0],
            ['code' => 'WELCOMEJKLMNO', 'status' => 'active', 'uses_remaining' => 1],
            ['code' => 'WELCOMEPQRSTU', 'status' => 'inactive', 'uses_remaining' => 1],
        ];

        if ($campaignIds['Welcome Discount'] ?? null) {
            foreach ($demoCodes as $code) {
                DB::table('coupon_codes')->updateOrInsert(
                    ['campaign_id' => $campaignIds['Welcome Discount'], 'code' => $code['code']],
                    array_merge($code, [
                        'campaign_id' => $campaignIds['Welcome Discount'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
            }

            // Seed one demo redemption
            $usedCodeId = DB::table('coupon_codes')
                ->where('campaign_id', $campaignIds['Welcome Discount'])
                ->where('code', 'WELCOME789GHI')
                ->value('id');

            if ($usedCodeId) {
                DB::table('coupon_redemptions')->updateOrInsert(
                    ['code_id' => $usedCodeId, 'user_id' => 1],
                    [
                        'code_id' => $usedCodeId,
                        'user_id' => 1,
                        'order_id' => 'demo-order-001',
                        'order_total' => 150.00,
                        'discount_amount' => 15.00,
                        'ip_address' => '192.168.1.100',
                        'redeemed_at' => now()->subDays(5),
                        'created_at' => now()->subDays(5),
                        'updated_at' => now()->subDays(5),
                    ]
                );
            }
        }

        // Seed demo codes for Summer Sale
        $summerCodes = [
            'SUMMER2025ABC1',
            'SUMMER2025DEF2',
            'SUMMER2025GHI3',
            'SUMMER2025JKL4',
            'SUMMER2025MNO5',
        ];

        if ($campaignIds['Summer Sale 2025'] ?? null) {
            foreach ($summerCodes as $code) {
                DB::table('coupon_codes')->updateOrInsert(
                    ['campaign_id' => $campaignIds['Summer Sale 2025'], 'code' => $code],
                    [
                        'campaign_id' => $campaignIds['Summer Sale 2025'],
                        'code' => $code,
                        'status' => 'active',
                        'uses_remaining' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // Seed demo codes for Free Shipping
        $freeShipCodes = ['SHIPFREE', 'SHIPNOW', 'SHIPFAST'];

        if ($campaignIds['Free Shipping'] ?? null) {
            foreach ($freeShipCodes as $code) {
                DB::table('coupon_codes')->updateOrInsert(
                    ['campaign_id' => $campaignIds['Free Shipping'], 'code' => $code],
                    [
                        'campaign_id' => $campaignIds['Free Shipping'],
                        'code' => $code,
                        'status' => 'active',
                        'uses_remaining' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('✓ Coupon Codes microservice seeded successfully');
        $this->command->info('  - Microservice metadata created');
        $this->command->info('  - ' . count($campaigns) . ' demo campaigns created');
        $this->command->info('  - ' . (count($demoCodes) + count($summerCodes) + count($freeShipCodes)) . ' demo codes created');
        $this->command->info('  - 1 demo redemption created');
    }
}
