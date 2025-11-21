<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class DoorSalesMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'door-sales'],
            [
                'name' => ['en' => 'Door Sales', 'ro' => 'Vânzări la Ușă'],
                'description' => [
                    'en' => 'Complete point-of-sale system for selling tickets at venue doors. Supports cash, card, and mobile payments with real-time inventory sync and receipt printing.',
                    'ro' => 'Sistem complet de vânzare la punct pentru vânzarea biletelor la intrarea locației. Suportă plăți numerar, card și mobile cu sincronizare în timp real a stocului și imprimare de chitanțe.'
                ],
                'short_description' => [
                    'en' => 'Sell tickets at the door with real-time sync',
                    'ro' => 'Vinde bilete la ușă cu sincronizare în timp real'
                ],
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Real-time ticket inventory synchronization',
                        'Multiple payment methods (cash, card, mobile)',
                        'Thermal receipt printing',
                        'Offline mode with automatic sync',
                        'Daily sales reports and reconciliation',
                        'Multiple device support per venue',
                        'Barcode/QR code ticket generation',
                        'Cash drawer management',
                        'Refund and exchange processing',
                        'Staff access controls and audit logs',
                        'Integration with main ticketing system',
                        'Customer lookup by phone/email',
                    ],
                    'ro' => [
                        'Sincronizare în timp real a stocului de bilete',
                        'Metode multiple de plată (numerar, card, mobil)',
                        'Imprimare termică de chitanțe',
                        'Mod offline cu sincronizare automată',
                        'Rapoarte zilnice de vânzări și reconciliere',
                        'Suport pentru multiple dispozitive per locație',
                        'Generare bilete cu cod de bare/QR',
                        'Gestionare sertar de numerar',
                        'Procesare rambursări și schimburi',
                        'Controale de acces personal și jurnale de audit',
                        'Integrare cu sistemul principal de ticketing',
                        'Căutare clienți după telefon/email',
                    ],
                ],
                'category' => 'sales',
                'status' => 'active',
                'metadata' => [
                    'endpoints' => [
                        'POST /api/door-sales/transaction',
                        'GET /api/door-sales/inventory/{eventId}',
                        'POST /api/door-sales/sync',
                        'GET /api/door-sales/reports/{tenantId}',
                        'POST /api/door-sales/refund',
                    ],
                    'supported_devices' => ['tablet', 'pos-terminal', 'mobile'],
                    'payment_processors' => ['stripe', 'square', 'sumup'],
                    'offline_capacity' => '1000 transactions',
                ],
            ]
        );

        $this->command->info('✓ Door Sales microservice seeded successfully');
    }
}
