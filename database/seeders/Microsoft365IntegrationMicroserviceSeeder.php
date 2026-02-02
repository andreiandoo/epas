<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Microsoft365IntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'microsoft365-integration'],
            [
                'name' => json_encode(['en' => 'Microsoft 365 Integration', 'ro' => 'Integrare Microsoft 365']),
                'description' => json_encode([
                    'en' => 'Connect with Microsoft 365 services including OneDrive, Outlook, and Teams. Upload files, send emails, and post messages to Teams channels from your platform.',
                    'ro' => 'Conectează-te cu serviciile Microsoft 365 incluzând OneDrive, Outlook și Teams. Încarcă fișiere, trimite emailuri și postează mesaje în canalele Teams din platformă.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Integrate with OneDrive, Outlook, and Teams',
                    'ro' => 'Integrează-te cu OneDrive, Outlook și Teams',
                ]),
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'OneDrive file uploads',
                        'File and folder management',
                        'Outlook email sending',
                        'Microsoft Teams messaging',
                        'Team and channel listing',
                        'OAuth 2.0 authentication',
                        'Automatic token refresh',
                        'Multi-service support',
                        'Calendar integration',
                    ],
                    'ro' => [
                        'Încărcare fișiere OneDrive',
                        'Gestionare fișiere și foldere',
                        'Trimitere email Outlook',
                        'Mesagerie Microsoft Teams',
                        'Listare echipe și canale',
                        'Autentificare OAuth 2.0',
                        'Reîmprospătare automată token',
                        'Suport servicii multiple',
                        'Integrare calendar',
                    ],
                ]),
                'category' => 'integration',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'services' => ['onedrive', 'outlook', 'teams', 'calendar'],
                    'database_tables' => ['microsoft365_connections', 'microsoft_onedrive_files', 'microsoft_outlook_messages', 'microsoft_teams_messages'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Microsoft 365 Integration microservice seeded successfully');
    }
}
