<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZapierIntegrationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'zapier-integration'],
            [
                'name' => json_encode(['en' => 'Zapier Integration', 'ro' => 'Integrare Zapier']),
                'description' => json_encode([
                    'en' => 'Connect your platform with 5000+ apps through Zapier. Trigger automated workflows when orders are placed, tickets are sold, customers register, or events are published.',
                    'ro' => 'Conectează-ți platforma cu 5000+ aplicații prin Zapier. Declanșează fluxuri automatizate când sunt plasate comenzi, vândute bilete, înregistrați clienți sau publicate evenimente.',
                ]),
                'short_description' => json_encode([
                    'en' => 'Automate workflows with 5000+ apps',
                    'ro' => 'Automatizează fluxuri cu 5000+ aplicații',
                ]),
                'price' => 20.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Order created trigger',
                        'Ticket sold trigger',
                        'Customer created trigger',
                        'Event published trigger',
                        'Registration completed trigger',
                        'Refund issued trigger',
                        'REST Hook subscriptions',
                        'Webhook delivery tracking',
                        'API key authentication',
                        'Trigger logging',
                    ],
                    'ro' => [
                        'Trigger comandă creată',
                        'Trigger bilet vândut',
                        'Trigger client creat',
                        'Trigger eveniment publicat',
                        'Trigger înregistrare completată',
                        'Trigger rambursare emisă',
                        'Subscripții REST Hook',
                        'Urmărire livrare webhook',
                        'Autentificare cheie API',
                        'Jurnal triggere',
                    ],
                ]),
                'category' => 'automation',
                'status' => 'active',
                'metadata' => json_encode([
                    'auth_type' => 'api_key',
                    'triggers' => ['order_created', 'ticket_sold', 'customer_created', 'event_published', 'registration_completed', 'refund_issued'],
                    'database_tables' => ['zapier_connections', 'zapier_triggers', 'zapier_trigger_logs', 'zapier_actions'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Zapier Integration microservice seeded successfully');
    }
}
