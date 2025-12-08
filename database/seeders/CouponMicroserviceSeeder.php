<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        $this->command->info('✓ Coupon Codes microservice metadata seeded successfully');
    }
}
