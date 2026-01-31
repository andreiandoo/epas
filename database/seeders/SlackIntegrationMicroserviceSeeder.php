<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlackIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'slack-integration'],
            [
                'name' => json_encode(['en' => 'Slack Integration', 'ro' => 'Integrare Slack']),
                'description' => json_encode([
                    'en' => 'Connect your platform with Slack workspaces. Send notifications, create channels, share updates, and keep your team informed about orders, events, and customer activities directly in Slack.',
                    'ro' => 'Conectează-ți platforma cu workspace-urile Slack. Trimite notificări, creează canale, partajează actualizări și ține-ți echipa informată despre comenzi, evenimente și activități clienți direct în Slack.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Send notifications and updates to Slack channels',
                    'ro' => 'Trimite notificări și actualizări în canalele Slack',
                ]),
                'price' => 10.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'OAuth 2.0 secure authentication',
                        'Send messages to any channel',
                        'Rich message formatting with blocks',
                        'File uploads and sharing',
                        'Add emoji reactions',
                        'Create and manage channels',
                        'Automatic channel syncing',
                        'Webhook notifications',
                        'Message history logging',
                        'Multiple workspace support',
                    ],
                    'ro' => [
                        'Autentificare securizată OAuth 2.0',
                        'Trimite mesaje în orice canal',
                        'Formatare mesaje avansată cu blocuri',
                        'Încărcare și partajare fișiere',
                        'Adaugă reacții emoji',
                        'Creează și gestionează canale',
                        'Sincronizare automată canale',
                        'Notificări webhook',
                        'Istoric mesaje',
                        'Suport multiple workspace-uri',
                    ],
                ]),
                'category' => 'integration',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'database_tables' => ['slack_connections', 'slack_channels', 'slack_messages', 'slack_webhooks'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Slack Integration microservice seeded successfully');
    }
}
