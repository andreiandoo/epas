<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhatsAppMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insert WhatsApp Notifications microservice metadata
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'whatsapp-notifications'],
            [
                'name' => 'Notificări WhatsApp',
                'description' => 'WhatsApp messaging for order confirmations, event reminders (D-7/D-3/D-1), and promotional campaigns. Includes opt-in management, template approval workflow, and BSP integration (360dialog/Twilio/etc.).',
                'price' => 0.00, // Pricing TBD (could be per-message or monthly)
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'usage', // Pay per message or monthly subscription
                'features' => json_encode([
                    'Order confirmation messages (idempotent)',
                    'Automatic event reminders at D-7, D-3, D-1',
                    'Promotional campaigns with segmentation',
                    'Opt-in/opt-out consent management (GDPR compliant)',
                    'Template management with BSP approval workflow',
                    'Multi-BSP support (360dialog, Twilio, Meta Cloud API)',
                    'Delivery receipts and read receipts',
                    'Rate limiting and throttling',
                    'Cost tracking and balance deduction',
                    'Webhook integration for status updates',
                    'E.164 phone number validation',
                    'Timezone-aware reminder scheduling',
                    'Variable templating {first_name}, {event_name}, etc.',
                    'Dry-run mode for testing campaigns',
                    'Comprehensive statistics and reporting',
                    'Message history and audit logs',
                ]),
                'category' => 'communication',
                'status' => 'active',
                'metadata' => json_encode([
                    'endpoints' => [
                        'POST /api/wa/templates',
                        'POST /api/wa/send/confirm',
                        'POST /api/wa/schedule/reminders',
                        'POST /api/wa/send/promo',
                        'POST /api/wa/webhook',
                        'POST /api/wa/optin',
                        'GET /api/wa/stats/{tenantId}',
                        'GET /api/wa/messages/{tenantId}',
                        'GET /api/wa/schedules/{tenantId}',
                    ],
                    'bsp_adapters' => ['mock', '360dialog', 'twilio', 'meta_cloud_api'],
                    'message_types' => ['order_confirm', 'reminder', 'promo', 'otp'],
                    'reminder_schedule' => ['D-7', 'D-3', 'D-1'],
                    'database_tables' => ['wa_optin', 'wa_templates', 'wa_messages', 'wa_schedules'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Seed demo opt-ins
        $tenantId = 'tenant_demo';

        DB::table('wa_optin')->updateOrInsert(
            ['tenant_id' => $tenantId, 'phone_e164' => '+40722123456'],
            [
                'user_ref' => 'user_001',
                'status' => 'opt_in',
                'source' => 'checkout',
                'consented_at' => now()->subDays(10),
                'metadata' => json_encode([
                    'ip' => '192.168.1.1',
                    'consent_text_version' => '1.0',
                ]),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ]
        );

        DB::table('wa_optin')->updateOrInsert(
            ['tenant_id' => $tenantId, 'phone_e164' => '+40722654321'],
            [
                'user_ref' => 'user_002',
                'status' => 'opt_in',
                'source' => 'settings_page',
                'consented_at' => now()->subDays(5),
                'metadata' => json_encode([
                    'ip' => '192.168.1.2',
                    'consent_text_version' => '1.0',
                ]),
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]
        );

        DB::table('wa_optin')->updateOrInsert(
            ['tenant_id' => $tenantId, 'phone_e164' => '+40722999888'],
            [
                'user_ref' => 'user_003',
                'status' => 'opt_out',
                'source' => 'checkout',
                'consented_at' => null,
                'metadata' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );

        // Seed demo templates
        DB::table('wa_templates')->insert([
            [
                'tenant_id' => $tenantId,
                'name' => 'order_confirmation',
                'language' => 'ro',
                'category' => 'order_confirm',
                'body' => 'Buna {first_name}! Comanda ta {order_code} pentru {event_name} a fost confirmata. Total: {total_amount}. Descarca biletele: {download_url}',
                'variables' => json_encode(['first_name', 'order_code', 'event_name', 'total_amount', 'download_url']),
                'status' => 'approved',
                'provider_meta' => json_encode([
                    'template_id' => 'tmpl_order_conf_001',
                ]),
                'submitted_at' => now()->subDays(15),
                'approved_at' => now()->subDays(14),
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(14),
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'event_reminder',
                'language' => 'ro',
                'category' => 'reminder',
                'body' => 'Salut {first_name}! Reminder: {event_name} este pe {event_date} la {event_time}. Locatie: {venue_name}, {venue_address}. Ne vedem acolo!',
                'variables' => json_encode(['first_name', 'event_name', 'event_date', 'event_time', 'venue_name', 'venue_address']),
                'status' => 'approved',
                'provider_meta' => json_encode([
                    'template_id' => 'tmpl_reminder_001',
                ]),
                'submitted_at' => now()->subDays(15),
                'approved_at' => now()->subDays(14),
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(14),
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'promo_discount',
                'language' => 'ro',
                'category' => 'promo',
                'body' => 'Buna {first_name}! Oferta speciala: {discount_code} - {discount_percent}% reducere la toate biletele! Valabil pana pe {expiry_date}. Shop now!',
                'variables' => json_encode(['first_name', 'discount_code', 'discount_percent', 'expiry_date']),
                'status' => 'approved',
                'provider_meta' => json_encode([
                    'template_id' => 'tmpl_promo_001',
                ]),
                'submitted_at' => now()->subDays(10),
                'approved_at' => now()->subDays(9),
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(9),
            ],
        ]);

        // Seed demo messages (insert separately due to different columns)
        DB::table('wa_messages')->insert([
            'tenant_id' => $tenantId,
            'type' => 'order_confirm',
            'to_phone' => '+40722123456',
            'template_name' => 'order_confirmation',
            'variables' => json_encode([
                'first_name' => 'Ion',
                'order_code' => 'ORD-2025-001',
                'event_name' => 'Concert Rock',
                'total_amount' => '200 RON',
                'download_url' => 'https://example.com/download/...',
            ]),
            'status' => 'delivered',
            'bsp_message_id' => 'wamid.demo_msg_001',
            'correlation_ref' => 'ORD-2025-001',
            'sent_at' => now()->subHours(5),
            'delivered_at' => now()->subHours(4)->subMinutes(58),
            'cost' => 0.005,
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(4)->subMinutes(58),
        ]);

        DB::table('wa_messages')->insert([
            'tenant_id' => $tenantId,
            'type' => 'reminder',
            'to_phone' => '+40722654321',
            'template_name' => 'event_reminder',
            'variables' => json_encode([
                'first_name' => 'Maria',
                'event_name' => 'Teatru Comedie',
                'event_date' => '5 Dec 2025',
                'event_time' => '19:00',
                'venue_name' => 'Teatrul Odeon',
                'venue_address' => 'Calea Victoriei 40, Bucuresti',
            ]),
            'status' => 'read',
            'bsp_message_id' => 'wamid.demo_msg_002',
            'correlation_ref' => 'ORD-2025-002',
            'sent_at' => now()->subHours(2),
            'delivered_at' => now()->subHours(1)->subMinutes(55),
            'read_at' => now()->subHours(1)->subMinutes(30),
            'cost' => 0.005,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(1)->subMinutes(30),
        ]);

        DB::table('wa_messages')->insert([
            'tenant_id' => $tenantId,
            'type' => 'promo',
            'to_phone' => '+40722123456',
            'template_name' => 'promo_discount',
            'variables' => json_encode([
                'first_name' => 'Ion',
                'discount_code' => 'SUMMER20',
                'discount_percent' => '20',
                'expiry_date' => '31 Dec 2025',
            ]),
            'status' => 'sent',
            'bsp_message_id' => 'wamid.demo_msg_003',
            'correlation_ref' => 'PROMO-2025-001',
            'sent_at' => now()->subMinutes(10),
            'cost' => 0.005,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        // Seed demo schedules
        DB::table('wa_schedules')->insert([
            [
                'tenant_id' => $tenantId,
                'message_type' => 'reminder_d7',
                'run_at' => now()->addDays(5),
                'payload' => json_encode([
                    'order_ref' => 'ORD-2025-003',
                    'phone' => '+40722123456',
                    'template_name' => 'event_reminder',
                    'variables' => [
                        'first_name' => 'Ion',
                        'event_name' => 'Festival Muzica',
                        'event_date' => '20 Dec 2025',
                        'event_time' => '18:00',
                        'venue_name' => 'Arena Nationala',
                        'venue_address' => 'Str. Basarabia 37-39, Bucuresti',
                    ],
                ]),
                'status' => 'scheduled',
                'correlation_ref' => 'ORD-2025-003',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'tenant_id' => $tenantId,
                'message_type' => 'reminder_d3',
                'run_at' => now()->addDays(9),
                'payload' => json_encode([
                    'order_ref' => 'ORD-2025-003',
                    'phone' => '+40722123456',
                    'template_name' => 'event_reminder',
                    'variables' => [
                        'first_name' => 'Ion',
                        'event_name' => 'Festival Muzica',
                        'event_date' => '20 Dec 2025',
                        'event_time' => '18:00',
                        'venue_name' => 'Arena Nationala',
                        'venue_address' => 'Str. Basarabia 37-39, Bucuresti',
                    ],
                ]),
                'status' => 'scheduled',
                'correlation_ref' => 'ORD-2025-003',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'tenant_id' => $tenantId,
                'message_type' => 'reminder_d1',
                'run_at' => now()->addDays(11),
                'payload' => json_encode([
                    'order_ref' => 'ORD-2025-003',
                    'phone' => '+40722123456',
                    'template_name' => 'event_reminder',
                    'variables' => [
                        'first_name' => 'Ion',
                        'event_name' => 'Festival Muzica',
                        'event_date' => '20 Dec 2025',
                        'event_time' => '18:00',
                        'venue_name' => 'Arena Nationala',
                        'venue_address' => 'Str. Basarabia 37-39, Bucuresti',
                    ],
                ]),
                'status' => 'scheduled',
                'correlation_ref' => 'ORD-2025-003',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
        ]);

        $this->command->info('✓ WhatsApp Notifications microservice seeded successfully');
        $this->command->info('  - Microservice metadata created');
        $this->command->info('  - 3 demo opt-ins created (2 opt-in, 1 opt-out)');
        $this->command->info('  - 3 demo templates created (order_confirmation, event_reminder, promo_discount)');
        $this->command->info('  - 3 demo messages created (confirmed, delivered, read statuses)');
        $this->command->info('  - 3 demo schedules created (D-7, D-3, D-1 reminders)');
    }
}
