<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class WaitlistMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'waitlist'],
            [
                'name' => ['en' => 'Waitlist Management', 'ro' => 'Gestionare Listă de Așteptare'],
                'description' => [
                    'en' => 'Capture demand for sold-out events with intelligent waitlist management. Automatically notify customers when tickets become available and convert waitlist entries to sales.',
                    'ro' => 'Captează cererea pentru evenimentele sold-out cu gestionare inteligentă a listei de așteptare. Notifică automat clienții când biletele devin disponibile și convertește înregistrările din lista de așteptare în vânzări.'
                ],
                'short_description' => [
                    'en' => 'Smart waitlist with automatic notifications',
                    'ro' => 'Listă de așteptare inteligentă cu notificări automate'
                ],
                'price' => 8.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Automatic waitlist activation on sellout',
                        'Priority queue management',
                        'Instant availability notifications',
                        'Time-limited purchase windows',
                        'Ticket type preferences',
                        'Quantity preferences per customer',
                        'SMS and email notifications',
                        'Waitlist analytics and conversion tracking',
                        'Fair distribution algorithms',
                        'VIP/loyalty priority options',
                        'Bulk release management',
                        'Customizable waitlist forms',
                        'Integration with refund releases',
                        'Waitlist position display for customers',
                    ],
                    'ro' => [
                        'Activare automată listă așteptare la sold-out',
                        'Gestionare coadă prioritate',
                        'Notificări instant disponibilitate',
                        'Ferestre de cumpărare cu timp limitat',
                        'Preferințe tip bilet',
                        'Preferințe cantitate per client',
                        'Notificări SMS și email',
                        'Analize și urmărire conversie listă așteptare',
                        'Algoritmi de distribuție echitabilă',
                        'Opțiuni prioritate VIP/loialitate',
                        'Gestionare eliberare în vrac',
                        'Formulare personalizabile listă așteptare',
                        'Integrare cu eliberări rambursare',
                        'Afișare poziție în listă pentru clienți',
                    ],
                ],
                'category' => 'sales',
                'status' => 'active',
                'metadata' => [
                    'endpoints' => [
                        'POST /api/waitlist/join',
                        'GET /api/waitlist/position/{customerId}',
                        'POST /api/waitlist/release/{eventId}',
                        'GET /api/waitlist/stats/{eventId}',
                        'DELETE /api/waitlist/leave/{customerId}',
                    ],
                    'notification_window' => '24 hours',
                    'purchase_window' => '2 hours',
                    'max_waitlist_size' => 10000,
                ],
            ]
        );

        $this->command->info('✓ Waitlist Management microservice seeded successfully');
    }
}
