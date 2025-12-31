<?php

namespace Database\Seeders;

use App\Models\Microservice;
use App\Models\MicroserviceFeature;
use Illuminate\Database\Seeder;

class WhatsAppCloudIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        $microservice = Microservice::updateOrCreate(
            ['slug' => 'whatsapp-cloud-integration'],
            [
                'name' => 'WhatsApp Business Cloud API',
                'description' => 'Direct integration with Meta\'s WhatsApp Business Cloud API for messaging (no BSP fees). Send order confirmations, ticket notifications, event reminders, and marketing campaigns via WhatsApp.',
                'category' => 'messaging',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'config_schema' => [
                    'phone_number_id' => [
                        'type' => 'string',
                        'label' => 'Phone Number ID',
                        'required' => true,
                        'description' => 'Your WhatsApp Business Phone Number ID from Meta Business Suite',
                    ],
                    'business_account_id' => [
                        'type' => 'string',
                        'label' => 'Business Account ID',
                        'required' => true,
                        'description' => 'Your WhatsApp Business Account ID (WABA)',
                    ],
                    'access_token' => [
                        'type' => 'secret',
                        'label' => 'Permanent Access Token',
                        'required' => true,
                        'description' => 'System User Access Token with whatsapp_business_messaging permission',
                    ],
                ],
                'required_env_vars' => [],
                'dependencies' => [],
                'documentation_url' => 'https://developers.facebook.com/docs/whatsapp/cloud-api',
            ]
        );

        $features = [
            [
                'name' => 'Text Messages',
                'slug' => 'text-messages',
                'description' => 'Send free-form text messages within 24-hour conversation window',
            ],
            [
                'name' => 'Template Messages',
                'slug' => 'template-messages',
                'description' => 'Send pre-approved template messages for notifications and marketing',
            ],
            [
                'name' => 'Media Messages',
                'slug' => 'media-messages',
                'description' => 'Send images, documents, videos, and audio files',
            ],
            [
                'name' => 'Order Confirmations',
                'slug' => 'order-confirmations',
                'description' => 'Automatic order confirmation messages via WhatsApp',
            ],
            [
                'name' => 'Ticket Notifications',
                'slug' => 'ticket-notifications',
                'description' => 'Send ticket confirmations and QR codes via WhatsApp',
            ],
            [
                'name' => 'Event Reminders',
                'slug' => 'event-reminders',
                'description' => 'Automated event reminder messages before events',
            ],
            [
                'name' => 'Webhook Integration',
                'slug' => 'webhook-integration',
                'description' => 'Receive inbound messages and delivery status updates',
            ],
            [
                'name' => 'Contact Management',
                'slug' => 'contact-management',
                'description' => 'Track opt-ins and conversation windows per contact',
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
