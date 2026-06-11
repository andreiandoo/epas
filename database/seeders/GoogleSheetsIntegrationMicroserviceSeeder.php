<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GoogleSheetsIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'google-sheets-integration'],
            [
                'name' => json_encode(['en' => 'Google Sheets Integration', 'ro' => 'Integrare Google Sheets']),
                'description' => json_encode([
                    'en' => 'Export and sync your orders, tickets, customers, and event data to Google Sheets. Create automated reports, track sales in real-time, and share live data with your team. Perfect for analytics, accounting, and team collaboration.',
                    'ro' => 'Exportă și sincronizează comenzile, biletele, clienții și datele evenimentelor în Google Sheets. Creează rapoarte automatizate, urmărește vânzările în timp real și partajează date live cu echipa ta. Perfect pentru analiză, contabilitate și colaborare.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Export orders and tickets to Google Sheets',
                    'ro' => 'Exportă comenzi și bilete în Google Sheets',
                ]),
                'price' => 12.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Export orders to Google Sheets',
                        'Export tickets with attendee details',
                        'Export customer lists',
                        'Export event analytics',
                        'Real-time data sync (new orders auto-append)',
                        'Scheduled sync (hourly, daily, weekly)',
                        'Custom column mapping',
                        'Automatic header formatting',
                        'Multiple spreadsheets per data type',
                        'Create new spreadsheets from dashboard',
                        'Sync history and job tracking',
                        'OAuth 2.0 secure authentication',
                        'Share sheets with team members',
                        'Full sync or incremental updates',
                    ],
                    'ro' => [
                        'Export comenzi în Google Sheets',
                        'Export bilete cu detalii participanți',
                        'Export liste clienți',
                        'Export analiză evenimente',
                        'Sincronizare în timp real (comenzi noi adăugate automat)',
                        'Sincronizare programată (orar, zilnic, săptămânal)',
                        'Mapare coloane personalizată',
                        'Formatare automată antet',
                        'Spreadsheet-uri multiple per tip de date',
                        'Creare spreadsheet-uri noi din dashboard',
                        'Istoric sincronizare și urmărire joburi',
                        'Autentificare securizată OAuth 2.0',
                        'Partajare sheet-uri cu echipa',
                        'Sincronizare completă sau actualizări incrementale',
                    ],
                ]),
                'category' => 'reporting',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'data_types' => ['orders', 'tickets', 'customers', 'events'],
                    'sync_modes' => ['full', 'incremental', 'append', 'realtime'],
                    'database_tables' => [
                        'google_sheets_connections',
                        'google_sheets_spreadsheets',
                        'google_sheets_sync_jobs',
                        'google_sheets_column_mappings',
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Google Sheets Integration microservice seeded successfully');
    }
}
