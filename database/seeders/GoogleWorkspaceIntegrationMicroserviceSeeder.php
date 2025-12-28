<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GoogleWorkspaceIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'google-workspace-integration'],
            [
                'name' => json_encode(['en' => 'Google Workspace Integration', 'ro' => 'Integrare Google Workspace']),
                'description' => json_encode([
                    'en' => 'Connect with Google Workspace services including Drive, Calendar, and Gmail. Upload documents, create calendar events, and send emails directly from your platform.',
                    'ro' => 'Conectează-te cu serviciile Google Workspace incluzând Drive, Calendar și Gmail. Încarcă documente, creează evenimente calendar și trimite emailuri direct din platformă.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Integrate with Google Drive, Calendar, and Gmail',
                    'ro' => 'Integrează-te cu Google Drive, Calendar și Gmail',
                ]),
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Google Drive file uploads',
                        'File and folder management',
                        'Google Calendar event creation',
                        'Calendar listing and sync',
                        'Gmail email sending',
                        'OAuth 2.0 authentication',
                        'Automatic token refresh',
                        'Multi-service support',
                        'File sharing permissions',
                    ],
                    'ro' => [
                        'Încărcare fișiere Google Drive',
                        'Gestionare fișiere și foldere',
                        'Creare evenimente Google Calendar',
                        'Listare și sincronizare calendar',
                        'Trimitere email Gmail',
                        'Autentificare OAuth 2.0',
                        'Reîmprospătare automată token',
                        'Suport servicii multiple',
                        'Permisiuni partajare fișiere',
                    ],
                ]),
                'category' => 'integration',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'services' => ['drive', 'calendar', 'gmail'],
                    'database_tables' => ['google_workspace_connections', 'google_drive_files', 'google_calendar_events', 'google_gmail_messages'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Google Workspace Integration microservice seeded successfully');
    }
}
