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
        ];

        foreach ($processors as $processor) {
            Microservice::updateOrCreate(
                ['slug' => $processor['slug']],
                $processor
            );
        }

        $this->command->info('Created 4 payment processor microservices');
    }
}
