<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class PaymentProcessorMicroservicesSeeder extends Seeder
{
    public function run(): void
    {
        $processors = [
            [
                'name' => ['en' => 'Stripe Integration', 'ro' => 'Integrare Stripe'],
                'slug' => 'payment-stripe',
                'short_description' => [
                    'en' => 'Accept payments via Stripe - cards, Apple Pay, Google Pay',
                    'ro' => 'Acceptă plăți prin Stripe - carduri, Apple Pay, Google Pay'
                ],
                'description' => [
                    'en' => 'Integrate Stripe as your payment processor. Accept credit/debit cards, Apple Pay, Google Pay, and more. You need an active Stripe account. Configure your API keys in Settings after activation.',
                    'ro' => 'Integrează Stripe ca procesator de plăți. Acceptă carduri de credit/debit, Apple Pay, Google Pay și altele. Ai nevoie de un cont Stripe activ. Configurează cheile API în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => ['en' => 'Netopia Integration', 'ro' => 'Integrare Netopia'],
                'slug' => 'payment-netopia',
                'short_description' => [
                    'en' => 'Accept payments via Netopia - popular in Romania',
                    'ro' => 'Acceptă plăți prin Netopia - popular în România'
                ],
                'description' => [
                    'en' => 'Integrate Netopia Payments as your payment processor. Popular choice for Romanian businesses. Supports cards, bank transfers. You need an active Netopia merchant account. Configure your credentials in Settings after activation.',
                    'ro' => 'Integrează Netopia Payments ca procesator de plăți. Alegere populară pentru afaceri din România. Suportă carduri, transferuri bancare. Ai nevoie de un cont de comerciant Netopia activ. Configurează credențialele în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => ['en' => 'PayU Integration', 'ro' => 'Integrare PayU'],
                'slug' => 'payment-payu',
                'short_description' => [
                    'en' => 'Accept payments via PayU - Central/Eastern Europe',
                    'ro' => 'Acceptă plăți prin PayU - Europa Centrală și de Est'
                ],
                'description' => [
                    'en' => 'Integrate PayU as your payment processor. Strong presence in Central and Eastern Europe. Supports multiple payment methods. You need an active PayU merchant account. Configure your credentials in Settings after activation.',
                    'ro' => 'Integrează PayU ca procesator de plăți. Prezență puternică în Europa Centrală și de Est. Suportă multiple metode de plată. Ai nevoie de un cont de comerciant PayU activ. Configurează credențialele în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => ['en' => 'Euplatesc Integration', 'ro' => 'Integrare Euplatesc'],
                'slug' => 'payment-euplatesc',
                'short_description' => [
                    'en' => 'Accept payments via Euplatesc - Romanian payment gateway',
                    'ro' => 'Acceptă plăți prin Euplatesc - gateway de plăți românesc'
                ],
                'description' => [
                    'en' => 'Integrate Euplatesc as your payment processor. Romanian payment gateway with competitive rates. Supports cards and local payment methods. You need an active Euplatesc merchant account. Configure your credentials in Settings after activation.',
                    'ro' => 'Integrează Euplatesc ca procesator de plăți. Gateway de plăți românesc cu rate competitive. Suportă carduri și metode de plată locale. Ai nevoie de un cont de comerciant Euplatesc activ. Configurează credențialele în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => ['en' => 'Revolut Integration', 'ro' => 'Integrare Revolut'],
                'slug' => 'payment-revolut',
                'short_description' => [
                    'en' => 'Accept payments via Revolut Business - modern fintech solution',
                    'ro' => 'Acceptă plăți prin Revolut Business - soluție fintech modernă'
                ],
                'description' => [
                    'en' => 'Integrate Revolut Business as your payment processor. Modern fintech solution with competitive rates and fast settlements. Supports cards and Revolut Pay. You need an active Revolut Business account with Merchant API access. Configure your API keys in Settings after activation.',
                    'ro' => 'Integrează Revolut Business ca procesator de plăți. Soluție fintech modernă cu rate competitive și decontări rapide. Suportă carduri și Revolut Pay. Ai nevoie de un cont Revolut Business activ cu acces la Merchant API. Configurează cheile API în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => ['en' => 'PayPal Integration', 'ro' => 'Integrare PayPal'],
                'slug' => 'payment-paypal',
                'short_description' => [
                    'en' => 'Accept payments via PayPal - trusted worldwide',
                    'ro' => 'Acceptă plăți prin PayPal - de încredere în întreaga lume'
                ],
                'description' => [
                    'en' => 'Integrate PayPal as your payment processor. Globally trusted payment platform supporting PayPal balance, cards, and Pay Later options. You need an active PayPal Business account. Configure your API credentials in Settings after activation.',
                    'ro' => 'Integrează PayPal ca procesator de plăți. Platformă de plată de încredere la nivel global care suportă sold PayPal, carduri și opțiuni Pay Later. Ai nevoie de un cont PayPal Business activ. Configurează credențialele API în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => ['en' => 'Klarna Integration', 'ro' => 'Integrare Klarna'],
                'slug' => 'payment-klarna',
                'short_description' => [
                    'en' => 'Accept payments via Klarna - buy now, pay later',
                    'ro' => 'Acceptă plăți prin Klarna - cumpără acum, plătește mai târziu'
                ],
                'description' => [
                    'en' => 'Integrate Klarna as your payment processor. Offer flexible payment options including Pay Later, Pay Now, and installment plans. Popular in Europe. You need an active Klarna merchant account. Configure your credentials in Settings after activation.',
                    'ro' => 'Integrează Klarna ca procesator de plăți. Oferă opțiuni de plată flexibile inclusiv Plătește Mai Târziu, Plătește Acum și planuri de rate. Popular în Europa. Ai nevoie de un cont de comerciant Klarna activ. Configurează credențialele în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => ['en' => 'SMS Payments', 'ro' => 'Plăți prin SMS'],
                'slug' => 'payment-sms',
                'short_description' => [
                    'en' => 'Send payment links via SMS to customers',
                    'ro' => 'Trimite linkuri de plată prin SMS către clienți'
                ],
                'description' => [
                    'en' => 'Send payment requests and reminders via SMS. Customers receive a secure payment link on their phone and can complete payment using any configured payment processor. Includes delivery tracking, scheduled reminders, and payment confirmations. Requires Twilio account for SMS delivery. Configure your Twilio credentials and fallback payment processor in Settings after activation.',
                    'ro' => 'Trimite cereri de plată și memento-uri prin SMS. Clienții primesc un link de plată securizat pe telefon și pot finaliza plata folosind orice procesator de plată configurat. Include urmărirea livrării, memento-uri programate și confirmări de plată. Necesită cont Twilio pentru livrarea SMS. Configurează credențialele Twilio și procesatorul de plată de rezervă în Setări după activare.'
                ],
                'pricing_model' => 'one_time',
                'price' => 0,
                'category' => 'payment_processor',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($processors as $processor) {
            Microservice::updateOrCreate(
                ['slug' => $processor['slug']],
                $processor
            );
        }

        $this->command->info('Created 8 payment processor microservices');
    }
}
