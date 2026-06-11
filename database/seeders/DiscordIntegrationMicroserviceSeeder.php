<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiscordIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'discord-integration'],
            [
                'name' => json_encode(['en' => 'Discord Integration', 'ro' => 'Integrare Discord']),
                'description' => json_encode([
                    'en' => 'Connect your platform with Discord servers. Send notifications via webhooks or bot, engage with your community, and automate announcements for events, sales, and updates.',
                    'ro' => 'Conectează-ți platforma cu serverele Discord. Trimite notificări prin webhookuri sau bot, interacționează cu comunitatea ta și automatizează anunțurile pentru evenimente, vânzări și actualizări.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Send notifications and updates to Discord servers',
                    'ro' => 'Trimite notificări și actualizări în serverele Discord',
                ]),
                'price' => 10.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Webhook message delivery',
                        'Bot integration support',
                        'Rich embed messages',
                        'Multiple server support',
                        'Channel management',
                        'Custom bot username and avatar',
                        'Message logging',
                        'Guild and channel listing',
                    ],
                    'ro' => [
                        'Livrare mesaje prin webhook',
                        'Suport integrare bot',
                        'Mesaje embed avansate',
                        'Suport servere multiple',
                        'Gestionare canale',
                        'Username și avatar bot personalizat',
                        'Istoric mesaje',
                        'Listare guild-uri și canale',
                    ],
                ]),
                'category' => 'integration',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'oauth2',
                    'database_tables' => ['discord_connections', 'discord_webhooks', 'discord_messages'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Discord Integration microservice seeded successfully');
    }
}
