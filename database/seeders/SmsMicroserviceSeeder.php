<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class SmsMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'sms-notifications'],
            [
                'name' => ['en' => 'SMS Notifications', 'ro' => 'Notificări SMS'],
                'description' => [
                    'en' => 'Send transactional and promotional SMS notifications to your event attendees. Automatic ticket confirmation SMS on purchase, promotional campaigns, and delivery reports.',
                    'ro' => 'Trimite notificări SMS tranzacționale și promoționale participanților la evenimentele tale. SMS automat de confirmare bilet la achiziție, campanii promoționale și rapoarte de livrare.',
                ],
                'short_description' => [
                    'en' => 'SMS notifications for ticket confirmations and promotions',
                    'ro' => 'Notificări SMS pentru confirmări bilete și promoții',
                ],
                'price' => 0,
                'currency' => 'EUR',
                'billing_cycle' => 'on_demand',
                'pricing_model' => 'usage',
                'category' => 'notifications',
                'is_active' => true,
                'sort_order' => 20,
                'features' => [
                    'en' => [
                        'Transactional SMS on ticket purchase',
                        'Promotional SMS campaigns',
                        'Delivery reports and tracking',
                        'Credit-based billing (pay as you go)',
                        'Phone number validation',
                        'Multi-network support (all Romanian operators)',
                    ],
                    'ro' => [
                        'SMS tranzacțional la achiziția de bilet',
                        'Campanii SMS promoționale',
                        'Rapoarte de livrare și tracking',
                        'Facturare pe bază de credit (plătești pe consum)',
                        'Validare numere de telefon',
                        'Suport multi-rețea (toți operatorii din România)',
                    ],
                ],
                'metadata' => [
                    'sms_pricing' => [
                        'transactional' => ['price' => 0.40, 'currency' => 'EUR', 'description' => 'Per SMS tranzacțional'],
                        'promotional' => ['price' => 0.50, 'currency' => 'EUR', 'description' => 'Per SMS promoțional'],
                    ],
                    'provider' => 'sendsms.ro',
                    'settings_schema' => [
                        'transactional_enabled' => ['type' => 'boolean', 'label' => 'SMS Tranzacționale', 'default' => false],
                        'promotional_enabled' => ['type' => 'boolean', 'label' => 'SMS Promoționale', 'default' => false],
                    ],
                ],
            ]
        );

        $this->command->info('✓ SMS Notifications microservice seeded successfully');
    }
}
