<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

/**
 * Registers the Flexible Payments umbrella microservice with three
 * toggleable sub-modules: installments, BNPL, delegated pay.
 *
 * Run: php artisan db:seed --class=FlexiblePaymentsMicroserviceSeeder
 */
class FlexiblePaymentsMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'flexible-payments'],
            [
                'name' => ['en' => 'Flexible Payments', 'ro' => 'Plăți flexibile'],
                'description' => [
                    'en' => 'Offer installments, Buy Now Pay Later, and delegated payment (someone else pays) at checkout — across your existing payment processor.',
                    'ro' => 'Oferă plata în rate, Buy Now Pay Later și plata delegată (plătește altcineva) în checkout — prin procesatorul de plată existent.',
                ],
                'short_description' => [
                    'en' => 'Installments, BNPL and delegated payment',
                    'ro' => 'Rate, BNPL și plată delegată',
                ],
                'icon' => 'heroicon-o-calendar-days',
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                // Deliberately NOT 'payment' — that category is reserved for actual
                // payment gateways (getDefaultPaymentMethod filters category='payment').
                'category' => 'finance',
                'features' => [
                    'en' => [
                        'Configurable installment plans',
                        'Down payment per event',
                        'Automatic card debiting (MIT)',
                        'Buy Now Pay Later (single deferred charge)',
                        'Delegated payment via secure 24h link',
                        'Payment reminders & dunning',
                        'Ticket issued invalid until fully paid',
                        'Incremental organizer payout',
                        'Refund handling with non-refundable fees',
                        'Flexible-payment analytics dashboard',
                    ],
                    'ro' => [
                        'Planuri de rate configurabile',
                        'Avans setat per eveniment',
                        'Debitare automată a cardului (MIT)',
                        'Buy Now Pay Later (o singură plată amânată)',
                        'Plată delegată prin link securizat 24h',
                        'Reminder-e de plată & dunning',
                        'Bilet emis invalid până la plata integrală',
                        'Payout incremental către organizator',
                        'Gestionare retur cu taxe nereturnabile',
                        'Dashboard de analytics pentru plăți flexibile',
                    ],
                ],
                'is_active' => true,
                'is_premium' => true,
                'config_schema' => [
                    'enable_installments' => true,
                    'enable_bnpl' => true,
                    'enable_delegated_pay' => true,
                    'platform_fee_percent_installments' => config('installments.platform_fee_percent_installments', 2.0),
                    'reminder_days_before' => config('installments.reminder_days_before', [7, 3, 1]),
                ],
            ]
        );
    }
}
