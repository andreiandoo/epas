<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TwilioIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'twilio-integration'],
            [
                'name' => json_encode(['en' => 'Twilio Integration', 'ro' => 'Integrare Twilio']),
                'description' => json_encode([
                    'en' => 'Send SMS, WhatsApp messages, and make voice calls through Twilio. Perfect for order confirmations, event reminders, and customer notifications with delivery tracking.',
                    'ro' => 'Trimite SMS-uri, mesaje WhatsApp și efectuează apeluri vocale prin Twilio. Perfect pentru confirmări comenzi, remindere evenimente și notificări clienți cu urmărirea livrării.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Send SMS, WhatsApp, and voice calls',
                    'ro' => 'Trimite SMS, WhatsApp și apeluri vocale',
                ]),
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'SMS messaging',
                        'WhatsApp messaging',
                        'Voice calls with TwiML',
                        'Delivery status tracking',
                        'Message history',
                        'Webhook status callbacks',
                        'Media message support',
                        'Cost tracking',
                        'Multiple phone numbers',
                    ],
                    'ro' => [
                        'Mesagerie SMS',
                        'Mesagerie WhatsApp',
                        'Apeluri vocale cu TwiML',
                        'Urmărire status livrare',
                        'Istoric mesaje',
                        'Callback-uri status webhook',
                        'Suport mesaje media',
                        'Urmărire costuri',
                        'Numere de telefon multiple',
                    ],
                ]),
                'category' => 'communication',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'api_key',
                    'channels' => ['sms', 'whatsapp', 'voice'],
                    'database_tables' => ['twilio_connections', 'twilio_messages', 'twilio_calls', 'twilio_webhooks'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Twilio Integration microservice seeded successfully');
    }
}
