<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesforceIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'salesforce-integration'],
            [
                'name' => json_encode(['en' => 'Salesforce Integration', 'ro' => 'Integrare Salesforce']),
                'description' => json_encode([
                    'en' => 'Sync your customer data with Salesforce CRM. Create and update contacts, leads, and opportunities. Keep your sales pipeline in sync with event ticket purchases and customer activities.',
                    'ro' => 'Sincronizează datele clienților cu Salesforce CRM. Creează și actualizează contacte, lead-uri și oportunități. Păstrează-ți pipeline-ul de vânzări sincronizat cu achizițiile de bilete și activitățile clienților.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Sync customers with Salesforce CRM',
                    'ro' => 'Sincronizează clienții cu Salesforce CRM',
                ]),
                'price' => 25.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Contact creation and sync',
                        'Lead management',
                        'Opportunity tracking',
                        'Custom field mapping',
                        'Bidirectional sync',
                        'SOQL query support',
                        'Sync logging and audit',
                        'OAuth 2.0 authentication',
                        'Automatic token refresh',
                    ],
                    'ro' => [
                        'Creare și sincronizare contacte',
                        'Gestionare lead-uri',
                        'Urmărire oportunități',
                        'Mapare câmpuri personalizată',
                        'Sincronizare bidirecțională',
                        'Suport query SOQL',
                        'Jurnal sincronizare și audit',
                        'Autentificare OAuth 2.0',
                        'Reîmprospătare automată token',
                    ],
                ]),
                'category' => 'crm',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'supported_objects' => ['Contact', 'Lead', 'Opportunity', 'Account'],
                    'database_tables' => ['salesforce_connections', 'salesforce_sync_logs', 'salesforce_field_mappings'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Salesforce Integration microservice seeded successfully');
    }
}
