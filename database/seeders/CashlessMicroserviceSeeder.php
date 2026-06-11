<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class CashlessMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'cashless'],
            [
                'name' => [
                    'en' => 'Cashless Festival',
                    'ro' => 'Festival Cashless',
                ],
                'description' => [
                    'en' => 'Complete cashless payment system for festivals. Includes digital wallets, wristband integration, vendor management, POS operations, top-ups (online & physical), cashouts, vouchers, real-time reports, supplier management, inventory tracking, and finance rules.',
                    'ro' => 'Sistem complet de plati cashless pentru festivaluri. Include portofele digitale, integrare bratari, gestiune vendori, operatiuni POS, alimentari (online si fizic), retrageri, vouchere, rapoarte real-time, gestiune furnizori, tracking stocuri si reguli financiare.',
                ],
                'short_description' => [
                    'en' => 'Cashless payment system for festivals & events',
                    'ro' => 'Sistem de plati cashless pentru festivaluri si evenimente',
                ],
                'price' => 99.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'category' => 'operations',
                'is_active' => true,
                'icon' => 'credit-card',
                'sort_order' => 10,
                'features' => [
                    'en' => [
                        'Digital wallet per customer per edition',
                        'NFC/QR wristband integration',
                        'Online & physical top-ups',
                        'Partial & full cashouts',
                        'Account-to-account transfers',
                        'Vendor management with roles (manager/supervisor/member)',
                        'POS device tracking & shift management',
                        'Product catalog with CSV import',
                        'Age-restricted product enforcement',
                        'Voucher & promo credit system',
                        'Supplier & inventory management',
                        'Finance rules (commissions, fees, pricing)',
                        'Real-time sales reports & dashboards',
                        'Offline POS with sync',
                        'SGR recycling tax support',
                        'Multi-currency support',
                    ],
                    'ro' => [
                        'Portofel digital per client per editie',
                        'Integrare bratari NFC/QR',
                        'Alimentari online si fizice',
                        'Retrageri partiale si totale',
                        'Transferuri intre conturi',
                        'Gestiune vendori cu roluri (manager/supervisor/membru)',
                        'Tracking dispozitive POS si gestiune ture',
                        'Catalog produse cu import CSV',
                        'Restrictii varsta pe produse',
                        'Sistem vouchere si credite promotionale',
                        'Gestiune furnizori si stocuri',
                        'Reguli financiare (comisioane, taxe, pricing)',
                        'Rapoarte vanzari real-time si dashboard-uri',
                        'POS offline cu sincronizare',
                        'Suport taxa SGR reciclare',
                        'Suport multi-moneda',
                    ],
                ],
                'metadata' => [
                    'endpoints' => [
                        '/api/cashless/accounts',
                        '/api/cashless/accounts/{id}/topup',
                        '/api/cashless/accounts/{id}/charge',
                        '/api/cashless/accounts/{id}/cashout',
                        '/api/cashless/accounts/{id}/transfer',
                        '/api/cashless/sales',
                        '/api/cashless/sales/by-category',
                        '/api/cashless/vendors',
                        '/api/cashless/vouchers/redeem',
                        '/api/cashless/reports',
                    ],
                    'database_tables' => [
                        'cashless_accounts',
                        'cashless_sales',
                        'cashless_settings',
                        'cashless_vouchers',
                        'cashless_voucher_redemptions',
                        'topup_locations',
                    ],
                    'integrations' => [
                        'wristbands',
                        'festival_editions',
                        'vendors',
                        'customers',
                        'payments',
                    ],
                    'required_tenant_type' => 'festival',
                ],
            ]
        );
    }
}
