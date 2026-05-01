<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

/**
 * Definește microserviciul "Extended Artist" în catalogul global.
 *
 * NU activează automat microserviciul pentru niciun marketplace_client —
 * acel pas se face manual de un super-admin la /admin/marketplace-clients/{id}/edit
 * → tab "Microservices" → checkbox "Extended Artist" → Save.
 *
 * Activarea per cont artist se face apoi din /marketplace/artist-accounts/{id}.
 */
class ExtendedArtistMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'extended-artist'],
            [
                'name' => [
                    'en' => 'Extended Artist',
                    'ro' => 'Extended Artist',
                ],
                'description' => [
                    'en' => 'Premium toolkit for marketplace artist accounts: deep audience analytics (Fan CRM), an open booking marketplace, a dynamic Smart EPK page, and an intelligent Tour Optimizer. Activated per artist account by the marketplace admin or self-purchased by the artist after a free trial.',
                    'ro' => 'Set premium de instrumente pentru conturile artist din marketplace: analiză profundă a publicului (Fan CRM), marketplace deschis de booking-uri, pagină EPK dinamică (Smart EPK) și optimizator inteligent de turnee (Tour Optimizer). Se activează per cont artist de către admin-ul marketplace sau este achiziționat de artist după un trial gratuit.',
                ],
                'short_description' => [
                    'en' => 'Premium artist toolkit: Fan CRM, Booking Marketplace, Smart EPK, Tour Optimizer.',
                    'ro' => 'Pachet premium pentru artiști: Fan CRM, Booking Marketplace, Smart EPK, Tour Optimizer.',
                ],
                'icon' => 'heroicon-o-sparkles',
                'category' => 'artist-tools',
                'version' => '1.0.0',
                'is_active' => true,
                'is_premium' => true,
                'pricing_model' => 'recurring',
                'billing_cycle' => 'monthly',
                'price' => 99.00,
                'currency' => 'RON',
                'features' => [
                    'en' => [
                        'Fan CRM (Audience Analytics)',
                        'Booking Marketplace',
                        'Smart EPK (Dynamic Press Kit)',
                        'Tour Optimizer',
                        '30-day free trial',
                        'Per-artist activation',
                    ],
                    'ro' => [
                        'Fan CRM (Analiză Public)',
                        'Booking Marketplace',
                        'Smart EPK (Press Kit dinamic)',
                        'Tour Optimizer',
                        'Trial gratuit 30 zile',
                        'Activare per artist',
                    ],
                ],
                'config_schema' => [
                    'trial_days' => [
                        'type' => 'integer',
                        'label' => 'Free Trial Days',
                        'required' => false,
                        'default' => 30,
                        'description' => 'Numărul de zile pentru trial-ul gratuit per cont artist.',
                    ],
                ],
                'metadata' => [
                    'audience' => 'artist',
                    'trial_days' => 30,
                    'modules' => [
                        'fan_crm' => [
                            'label' => 'Fan CRM',
                            'description' => 'Audience analytics, geographic heatmap, segments, retention cohorts.',
                            'launch_phase' => 2,
                        ],
                        'booking_marketplace' => [
                            'label' => 'Booking Marketplace',
                            'description' => 'Discoverable artist listings, booking requests, contracts, reviews.',
                            'launch_phase' => 5,
                        ],
                        'smart_epk' => [
                            'label' => 'Smart EPK',
                            'description' => 'Dynamic Electronic Press Kit page with live stats, multiple variants, branding.',
                            'launch_phase' => 2,
                        ],
                        'tour_optimizer' => [
                            'label' => 'Tour Optimizer',
                            'description' => 'Strategic tour planning with fan-density heatmap and route optimization.',
                            'launch_phase' => 4,
                        ],
                    ],
                ],
                'required_env_vars' => [],
                'dependencies' => [],
                'documentation_url' => null,
            ]
        );
    }
}
