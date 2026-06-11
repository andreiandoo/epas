<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HubSpotIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'hubspot-integration'],
            [
                'name' => json_encode(['en' => 'HubSpot Integration', 'ro' => 'Integrare HubSpot']),
                'description' => json_encode([
                    'en' => 'Sync your customer data with HubSpot CRM. Manage contacts, deals, and companies. Automate your marketing and sales workflows based on event attendance and purchase history.',
                    'ro' => 'Sincronizează datele clienților cu HubSpot CRM. Gestionează contacte, deal-uri și companii. Automatizează fluxurile de marketing și vânzări bazate pe participarea la evenimente și istoricul achizițiilor.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Sync customers with HubSpot CRM',
                    'ro' => 'Sincronizează clienții cu HubSpot CRM',
                ]),
                'price' => 20.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Contact management',
                        'Deal creation and tracking',
                        'Company records',
                        'Property mapping',
                        'Search and filter records',
                        'Bidirectional sync',
                        'Sync logging',
                        'OAuth 2.0 authentication',
                        'Webhook support',
                    ],
                    'ro' => [
                        'Gestionare contacte',
                        'Creare și urmărire deal-uri',
                        'Înregistrări companii',
                        'Mapare proprietăți',
                        'Căutare și filtrare înregistrări',
                        'Sincronizare bidirecțională',
                        'Jurnal sincronizare',
                        'Autentificare OAuth 2.0',
                        'Suport webhookuri',
                    ],
                ]),
                'category' => 'crm',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'supported_objects' => ['contacts', 'deals', 'companies', 'tickets'],
                    'database_tables' => ['hubspot_connections', 'hubspot_sync_logs', 'hubspot_property_mappings'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ HubSpot Integration microservice seeded successfully');
    }
}
