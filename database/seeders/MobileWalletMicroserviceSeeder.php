<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class MobileWalletMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'mobile-wallet'],
            [
                'name' => ['en' => 'Mobile Wallet Passes', 'ro' => 'Carduri Mobile Wallet'],
                'description' => [
                    'en' => 'Generate Apple Wallet and Google Pay passes for tickets. Customers can add tickets to their phone wallet for easy access and real-time updates.',
                    'ro' => 'Generează carduri Apple Wallet și Google Pay pentru bilete. Clienții pot adăuga biletele în portofelul telefonului pentru acces ușor și actualizări în timp real.'
                ],
                'short_description' => [
                    'en' => 'Apple Wallet & Google Pay ticket passes',
                    'ro' => 'Carduri pentru bilete Apple Wallet și Google Pay'
                ],
                'price' => 10.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Apple Wallet (.pkpass) generation',
                        'Google Pay pass generation',
                        'Custom pass design with branding',
                        'Real-time pass updates (time, venue changes)',
                        'Push notifications to wallet',
                        'Location-based reminders',
                        'Barcode/QR code on pass',
                        'Multiple ticket types per pass',
                        'Automatic pass delivery via email',
                        'Pass analytics and engagement tracking',
                        'Batch pass generation',
                        'Pass expiration management',
                        'Integration with ticket scanner',
                        'Support for event series passes',
                    ],
                    'ro' => [
                        'Generare Apple Wallet (.pkpass)',
                        'Generare card Google Pay',
                        'Design personalizat card cu branding',
                        'Actualizări în timp real card (oră, schimbări locație)',
                        'Notificări push în wallet',
                        'Remindere bazate pe locație',
                        'Cod de bare/QR pe card',
                        'Tipuri multiple de bilete per card',
                        'Livrare automată card prin email',
                        'Analize card și urmărire engagement',
                        'Generare carduri în lot',
                        'Gestionare expirare carduri',
                        'Integrare cu scanner bilete',
                        'Suport pentru carduri serii evenimente',
                    ],
                ],
                'category' => 'distribution',
                'status' => 'active',
                'metadata' => [
                    'endpoints' => [
                        'POST /api/wallet/generate/{ticketId}',
                        'GET /api/wallet/pass/{passId}',
                        'POST /api/wallet/update/{passId}',
                        'POST /api/wallet/notify/{passId}',
                        'GET /api/wallet/analytics/{tenantId}',
                    ],
                    'supported_platforms' => ['apple-wallet', 'google-pay'],
                    'pass_formats' => ['pkpass', 'jwt'],
                    'max_passes_per_event' => 'unlimited',
                ],
            ]
        );

        $this->command->info('✓ Mobile Wallet Passes microservice seeded successfully');
    }
}
