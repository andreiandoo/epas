<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class AnalyticsMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'analytics'],
            [
                'name' => ['en' => 'Advanced Analytics', 'ro' => 'Analiză Avansată'],
                'description' => [
                    'en' => 'Comprehensive analytics dashboard with real-time sales tracking, audience insights, revenue forecasting, and custom report builder. Make data-driven decisions for your events.',
                    'ro' => 'Tablou de bord analitic cuprinzător cu urmărirea vânzărilor în timp real, informații despre audiență, previziuni de venituri și generator de rapoarte personalizate. Ia decizii bazate pe date pentru evenimentele tale.'
                ],
                'short_description' => [
                    'en' => 'Real-time analytics and custom reporting',
                    'ro' => 'Analize în timp real și raportare personalizată'
                ],
                'price' => 20.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Real-time sales dashboard',
                        'Revenue forecasting with ML',
                        'Audience demographics analysis',
                        'Conversion funnel tracking',
                        'Custom report builder',
                        'Automated report scheduling',
                        'Export to PDF, Excel, CSV',
                        'Compare events performance',
                        'Geographic sales heatmaps',
                        'Traffic source attribution',
                        'Ticket type performance analysis',
                        'Promo code effectiveness tracking',
                        'Refund and cancellation analytics',
                        'Year-over-year comparisons',
                        'API access for custom integrations',
                    ],
                    'ro' => [
                        'Tablou de bord vânzări în timp real',
                        'Previziuni venituri cu ML',
                        'Analiză demografică audiență',
                        'Urmărire pâlnie de conversie',
                        'Constructor rapoarte personalizate',
                        'Programare automată rapoarte',
                        'Export în PDF, Excel, CSV',
                        'Comparare performanță evenimente',
                        'Hărți termice vânzări geografice',
                        'Atribuire surse trafic',
                        'Analiză performanță tipuri bilete',
                        'Urmărire eficiență coduri promoționale',
                        'Analize rambursări și anulări',
                        'Comparații an la an',
                        'Acces API pentru integrări personalizate',
                    ],
                ],
                'category' => 'analytics',
                'status' => 'active',
                'metadata' => [
                    'endpoints' => [
                        'GET /api/analytics/dashboard/{tenantId}',
                        'GET /api/analytics/events/{eventId}',
                        'POST /api/analytics/reports',
                        'GET /api/analytics/forecast/{eventId}',
                        'GET /api/analytics/funnel/{eventId}',
                    ],
                    'data_retention' => '24 months',
                    'refresh_interval' => '5 minutes',
                    'export_formats' => ['pdf', 'xlsx', 'csv', 'json'],
                ],
            ]
        );

        $this->command->info('✓ Analytics microservice seeded successfully');
    }
}
