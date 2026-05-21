<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class LeisureMicroservicesSeeder extends Seeder
{
    public function run(): void
    {
        $microservices = [
            [
                'slug' => 'leisure-core',
                'name' => [
                    'en' => 'Leisure Core',
                    'ro' => 'Leisure Core',
                ],
                'short_description' => [
                    'en' => 'Core leisure features: capacity calendar, subscription tickets, daily passes.',
                    'ro' => 'Funcționalitate de bază leisure: calendar de capacitate, abonamente, bilete pe zi.',
                ],
                'description' => [
                    'en' => 'Enables the core leisure venue capabilities: date-based capacity, subscription tickets, single-day passes, season pricing rules.',
                    'ro' => 'Activează capabilitățile de bază pentru locații de agrement: capacity per dată, abonamente, bilete single-day, reguli pricing pe sezon.',
                ],
                'category' => 'leisure',
                'sort_order' => 100,
            ],
            [
                'slug' => 'leisure-pos',
                'name' => [
                    'en' => 'Leisure POS',
                    'ro' => 'Leisure POS',
                ],
                'short_description' => [
                    'en' => 'On-site ticket sales with thermal receipt printing.',
                    'ro' => 'Vânzare bilete la fața locului cu chitanță termică.',
                ],
                'description' => [
                    'en' => 'Tablet-first POS interface for operators at the entrance/cashier. Cash & card support, browser-based thermal receipt (80mm PDF).',
                    'ro' => 'Interfață POS tablet-first pentru operatori la intrare/casierie. Suport cash & card, chitanță termică prin browser (PDF 80mm).',
                ],
                'category' => 'leisure',
                'sort_order' => 110,
            ],
            [
                'slug' => 'leisure-rentals',
                'name' => [
                    'en' => 'Leisure Rentals',
                    'ro' => 'Leisure Rentals',
                ],
                'short_description' => [
                    'en' => 'Variable-duration rentals with automatic overtime surcharge.',
                    'ro' => 'Rentals cu durate variabile și surcharge automat la depășire.',
                ],
                'description' => [
                    'en' => 'Manage rentable services (boats, kayaks, bikes, etc.) with multiple duration variants. Tracks active rentals, calculates overtime surcharges automatically.',
                    'ro' => 'Gestionează servicii de închiriat (bărci, kayak, biciclete etc.) cu durate variabile multiple. Urmărește rentals active, calculează automat surcharge la depășire.',
                ],
                'category' => 'leisure',
                'sort_order' => 120,
            ],
            [
                'slug' => 'leisure-multi-society',
                'name' => [
                    'en' => 'Multi-Society',
                    'ro' => 'Multi-Societate',
                ],
                'short_description' => [
                    'en' => 'Issue invoices from multiple legal entities under one tenant.',
                    'ro' => 'Emite facturi pe mai multe CIF-uri sub același tenant.',
                ],
                'description' => [
                    'en' => 'Allows assigning products to different tax registries (legal entities). Useful when entry tickets and rentals belong to different SRLs.',
                    'ro' => 'Permite asignarea produselor pe diferite societăți fiscale. Util când biletele de acces și rentals țin de SRL-uri diferite.',
                ],
                'category' => 'leisure',
                'sort_order' => 130,
            ],
            [
                'slug' => 'leisure-embed',
                'name' => [
                    'en' => 'Leisure Embed Widget',
                    'ro' => 'Widget Embed Leisure',
                ],
                'short_description' => [
                    'en' => 'Embeddable ticketing widget for external websites.',
                    'ro' => 'Widget de ticketing embedabil pentru website-uri externe.',
                ],
                'description' => [
                    'en' => 'JavaScript snippet to embed Tixello ticketing on the tenant\'s own website. Calendar picker, ticket selection, full checkout flow.',
                    'ro' => 'Snippet JavaScript pentru a integra ticketing Tixello pe website-ul propriu al tenant-ului. Calendar picker, selecție bilete, checkout complet.',
                ],
                'category' => 'leisure',
                'sort_order' => 140,
            ],
        ];

        foreach ($microservices as $data) {
            Microservice::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'is_active' => true,
                    'is_premium' => false,
                    'price' => 0,
                    'currency' => 'RON',
                    'billing_cycle' => 'monthly',
                    'pricing_model' => 'recurring',
                    'version' => '1.0.0',
                ])
            );
        }
    }
}
