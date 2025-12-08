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
        // Migration: id (UUID), tenant_id, name (JSON), description (JSON), status, starts_at, ends_at,
        //           budget_limit, budget_used, redemption_limit, redemption_count, metadata, created_by
        $campaigns = [
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'Welcome Discount', 'ro' => 'Reducere de Bun Venit']),
                'description' => json_encode(['en' => 'Welcome discount for new customers', 'ro' => 'Reducere pentru clienți noi']),
                'status' => 'active',
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addDays(60),
                'redemption_limit' => 1000,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'Summer Sale 2025', 'ro' => 'Reduceri de Vară 2025']),
                'description' => json_encode(['en' => 'Summer promotional campaign', 'ro' => 'Campanie promoțională de vară']),
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonths(3),
                'budget_limit' => 5000.00,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'Free Shipping', 'ro' => 'Livrare Gratuită']),
                'description' => json_encode(['en' => 'Free shipping on orders over 75 EUR', 'ro' => 'Livrare gratuită la comenzi peste 75 EUR']),
                'status' => 'active',
                'starts_at' => null,
                'ends_at' => null,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'name' => json_encode(['en' => 'VIP Exclusive', 'ro' => 'Exclusiv VIP']),
                'description' => json_encode(['en' => 'Exclusive codes for VIP customers', 'ro' => 'Coduri exclusive pentru clienți VIP']),
                'status' => 'draft',
                'starts_at' => null,
                'ends_at' => null,
            ],
        ];

        $campaignIds = [];
        foreach ($campaigns as $campaign) {
            $name = json_decode($campaign['name'], true)['en'];
            DB::table('coupon_campaigns')->updateOrInsert(
                ['id' => $campaign['id']],
                array_merge($campaign, ['created_at' => now(), 'updated_at' => now()])
            );
            $campaignIds[$name] = $campaign['id'];
        }

        // Seed demo codes
        // Migration: id (UUID), tenant_id, campaign_id, code, code_type, discount_type, discount_value,
        //           max_discount_amount, min_purchase_amount, max_uses_total, max_uses_per_user,
        //           current_uses, first_purchase_only, starts_at, expires_at, status, combinable
        $demoCodes = [
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignIds['Welcome Discount'],
                'code' => 'WELCOME123ABC',
                'code_type' => 'single_use',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'min_purchase_amount' => 50.00,
                'max_discount_amount' => 100.00,
                'max_uses_total' => 1,
                'max_uses_per_user' => 1,
                'current_uses' => 0,
                'first_purchase_only' => true,
                'status' => 'active',
                'combinable' => false,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignIds['Welcome Discount'],
                'code' => 'WELCOME456DEF',
                'code_type' => 'single_use',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'min_purchase_amount' => 50.00,
                'max_discount_amount' => 100.00,
                'max_uses_total' => 1,
                'max_uses_per_user' => 1,
                'current_uses' => 0,
                'first_purchase_only' => true,
                'status' => 'active',
                'combinable' => false,
            ],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignIds['Welcome Discount'],
                'code' => 'WELCOME789GHI',
                'code_type' => 'single_use',
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'min_purchase_amount' => 50.00,
                'max_discount_amount' => 100.00,
                'max_uses_total' => 1,
                'max_uses_per_user' => 1,
                'current_uses' => 1,
                'first_purchase_only' => true,
                'status' => 'exhausted',
                'combinable' => false,
            ],
        ];

        $codeIds = [];
        foreach ($demoCodes as $code) {
            DB::table('coupon_codes')->updateOrInsert(
                ['tenant_id' => $code['tenant_id'], 'code' => $code['code']],
                array_merge($code, ['created_at' => now(), 'updated_at' => now()])
            );
            $codeIds[$code['code']] = $code['id'];
        }

        // Seed one demo redemption
        // Migration: id (UUID), tenant_id, coupon_id, user_id, order_id, discount_applied,
        //           original_amount, final_amount, currency, status
        DB::table('coupon_redemptions')->updateOrInsert(
            ['id' => Str::uuid()->toString()],
            [
                'id' => Str::uuid()->toString(),
                'tenant_id' => $tenantId,
                'coupon_id' => $codeIds['WELCOME789GHI'],
                'user_id' => 1,
                'order_id' => 'demo-order-001',
                'discount_applied' => 15.00,
                'original_amount' => 150.00,
                'final_amount' => 135.00,
                'currency' => 'EUR',
                'status' => 'completed',
                'ip_address' => '192.168.1.100',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]
        );

        // Seed demo codes for Summer Sale
        $summerCodes = ['SUMMER2025ABC1', 'SUMMER2025DEF2', 'SUMMER2025GHI3'];
        foreach ($summerCodes as $code) {
            DB::table('coupon_codes')->updateOrInsert(
                ['tenant_id' => $tenantId, 'code' => $code],
                [
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenantId,
                    'campaign_id' => $campaignIds['Summer Sale 2025'],
                    'code' => $code,
                    'code_type' => 'multi_use',
                    'discount_type' => 'percentage',
                    'discount_value' => 20.00,
                    'min_purchase_amount' => 100.00,
                    'max_discount_amount' => 200.00,
                    'max_uses_per_user' => 3,
                    'current_uses' => 0,
                    'status' => 'active',
                    'combinable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Seed demo codes for Free Shipping
        $freeShipCodes = ['SHIPFREE', 'SHIPNOW', 'SHIPFAST'];
        foreach ($freeShipCodes as $code) {
            DB::table('coupon_codes')->updateOrInsert(
                ['tenant_id' => $tenantId, 'code' => $code],
                [
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $tenantId,
                    'campaign_id' => $campaignIds['Free Shipping'],
                    'code' => $code,
                    'code_type' => 'multi_use',
                    'discount_type' => 'free_shipping',
                    'discount_value' => 0,
                    'min_purchase_amount' => 75.00,
                    'max_uses_per_user' => 5,
                    'current_uses' => 0,
                    'status' => 'active',
                    'combinable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $totalCodes = count($demoCodes) + count($summerCodes) + count($freeShipCodes);
        $this->command->info('✓ Coupon Codes microservice seeded successfully');
        $this->command->info('  - Microservice metadata created');
        $this->command->info('  - ' . count($campaigns) . ' demo campaigns created');
        $this->command->info('  - ' . $totalCodes . ' demo codes created');
        $this->command->info('  - 1 demo redemption created');
    }
}
