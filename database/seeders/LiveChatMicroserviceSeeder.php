<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

/**
 * Seeds the `live-chat` microservice definition. Activation is per marketplace
 * (marketplace_client_microservices pivot); this only registers the catalog row.
 *
 *   php artisan db:seed --class=Database\\Seeders\\LiveChatMicroserviceSeeder
 */
class LiveChatMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'live-chat'],
            [
                'name' => ['en' => 'Live Chat', 'ro' => 'Chat live'],
                'description' => [
                    'en' => 'Real-time chat between site visitors (customers, organizers, guests) and marketplace operators, with operator scheduling, queueing, canned responses, page/order context, transcripts and anti-bot protection.',
                    'ro' => 'Chat în timp real între vizitatorii site-ului (clienți, organizatori, vizitatori anonimi) și operatorii marketplace-ului, cu program pentru operatori, coadă de așteptare, răspunsuri predefinite, context pagină/comandă, transcript și protecție anti-bot.',
                ],
                'short_description' => [
                    'en' => 'Real-time visitor ↔ operator chat with scheduling and queueing',
                    'ro' => 'Chat în timp real vizitator ↔ operator, cu program și coadă',
                ],
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Floating chat widget on the whole site',
                        'Customer, organizer and anonymous guest support',
                        'Organizer badge (same queue, distinct label)',
                        'Automatic page / event / cart / order context',
                        'Multiple operators with weekly schedules',
                        'Queue with position + estimated wait',
                        'Offline / out-of-hours leave-a-message flow',
                        'Operator console in the admin panel',
                        'Canned responses / macros',
                        'Internal notes, transfer and escalation',
                        'Typing indicators, read receipts, presence',
                        'File attachments (image / PDF)',
                        'Post-chat rating and email transcript',
                        'Anti-bot: honeypot, rate limiting, blocklist',
                        'Polling transport with optional Reverb upgrade',
                    ],
                    'ro' => [
                        'Casetă de chat flotantă pe tot site-ul',
                        'Suport pentru clienți, organizatori și vizitatori anonimi',
                        'Badge organizator (aceeași coadă, etichetă distinctă)',
                        'Context automat pagină / eveniment / coș / comandă',
                        'Mai mulți operatori cu program săptămânal',
                        'Coadă cu poziție + timp estimat de așteptare',
                        'Flux „lasă un mesaj" în afara programului',
                        'Consolă operator în panoul de administrare',
                        'Răspunsuri predefinite / macro-uri',
                        'Note interne, transfer și escaladare',
                        'Indicator tastare, confirmări citire, prezență',
                        'Atașamente (imagine / PDF)',
                        'Rating după conversație și transcript pe email',
                        'Anti-bot: honeypot, rate limiting, blocklist',
                        'Transport polling cu upgrade opțional la Reverb',
                    ],
                ],
                'category' => 'communication',
                'status' => 'active',
                'is_active' => true,
                'metadata' => [
                    'config_key' => 'live-chat',
                    'settings_page' => 'microservices/live-chat/settings',
                    'operator_permission' => 'chat.operate',
                    'transports' => ['polling', 'reverb'],
                ],
            ]
        );

        $this->command->info('✓ Live Chat microservice seeded successfully');
    }
}
