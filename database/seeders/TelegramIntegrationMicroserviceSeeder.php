<?php

namespace Database\Seeders;

use App\Models\Microservice;
use App\Models\MicroserviceFeature;
use Illuminate\Database\Seeder;

class TelegramIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        $microservice = Microservice::updateOrCreate(
            ['slug' => 'telegram-integration'],
            [
                'name' => 'Telegram Bot Integration',
                'description' => 'Connect a Telegram Bot to send notifications, updates, and interact with customers. Supports broadcast messaging, inline keyboards, and webhook updates.',
                'category' => 'messaging',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => false,
                'config_schema' => [
                    'bot_token' => [
                        'type' => 'secret',
                        'label' => 'Bot Token',
                        'required' => true,
                        'description' => 'Bot token from @BotFather on Telegram',
                    ],
                ],
                'required_env_vars' => [],
                'dependencies' => [],
                'documentation_url' => 'https://core.telegram.org/bots/api',
            ]
        );

        $features = [
            [
                'name' => 'Bot Messaging',
                'slug' => 'bot-messaging',
                'description' => 'Send text messages, photos, documents, and media via bot',
            ],
            [
                'name' => 'Inline Keyboards',
                'slug' => 'inline-keyboards',
                'description' => 'Interactive buttons for user actions and callbacks',
            ],
            [
                'name' => 'Broadcast Messages',
                'slug' => 'broadcast-messages',
                'description' => 'Send announcements to all bot subscribers',
            ],
            [
                'name' => 'Channel Integration',
                'slug' => 'channel-integration',
                'description' => 'Post updates to Telegram channels automatically',
            ],
            [
                'name' => 'Subscriber Management',
                'slug' => 'subscriber-management',
                'description' => 'Track users who started the bot',
            ],
            [
                'name' => 'Webhook Updates',
                'slug' => 'webhook-updates',
                'description' => 'Receive and process incoming messages via webhooks',
            ],
            [
                'name' => 'Order Notifications',
                'slug' => 'order-notifications',
                'description' => 'Send order confirmations and updates via Telegram',
            ],
            [
                'name' => 'Event Reminders',
                'slug' => 'event-reminders',
                'description' => 'Automated event reminder messages',
            ],
        ];

        foreach ($features as $feature) {
            MicroserviceFeature::updateOrCreate(
                ['microservice_id' => $microservice->id, 'slug' => $feature['slug']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
