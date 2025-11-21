<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class CrmMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'crm'],
            [
                'name' => ['en' => 'CRM', 'ro' => 'CRM'],
                'description' => [
                    'en' => 'Customer Relationship Management system tailored for event organizers. Track customer interactions, segment audiences, manage communications, and analyze customer lifetime value.',
                    'ro' => 'Sistem de gestionare a relațiilor cu clienții adaptat pentru organizatorii de evenimente. Urmărește interacțiunile cu clienții, segmentează audiențele, gestionează comunicările și analizează valoarea pe viață a clienților.'
                ],
                'short_description' => [
                    'en' => 'Manage customer relationships and communications',
                    'ro' => 'Gestionează relațiile cu clienții și comunicările'
                ],
                'price' => 25.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Unified customer profiles with purchase history',
                        'Advanced audience segmentation',
                        'Automated email campaigns',
                        'SMS marketing integration',
                        'Customer lifetime value tracking',
                        'Event attendance history',
                        'Custom tags and labels',
                        'Import/export customer data (CSV, Excel)',
                        'GDPR compliance tools',
                        'Duplicate detection and merging',
                        'Customer notes and activity timeline',
                        'Integration with email templates',
                        'Abandoned cart recovery',
                        'VIP customer identification',
                    ],
                    'ro' => [
                        'Profiluri unificate de clienți cu istoric de achiziții',
                        'Segmentare avansată a audienței',
                        'Campanii automatizate de email',
                        'Integrare marketing SMS',
                        'Urmărire valoare pe viață client',
                        'Istoric participare la evenimente',
                        'Etichete și taguri personalizate',
                        'Import/export date clienți (CSV, Excel)',
                        'Instrumente conformitate GDPR',
                        'Detectare și fuzionare duplicate',
                        'Note client și timeline activitate',
                        'Integrare cu șabloane email',
                        'Recuperare coșuri abandonate',
                        'Identificare clienți VIP',
                    ],
                ],
                'category' => 'crm',
                'status' => 'active',
                'metadata' => [
                    'endpoints' => [
                        'GET /api/crm/customers',
                        'GET /api/crm/customers/{id}',
                        'POST /api/crm/segments',
                        'GET /api/crm/segments/{id}/customers',
                        'POST /api/crm/campaigns',
                        'GET /api/crm/analytics/ltv',
                    ],
                    'integrations' => ['mailchimp', 'sendgrid', 'twilio'],
                    'max_contacts' => 'unlimited',
                    'max_segments' => 100,
                ],
            ]
        );

        $this->command->info('✓ CRM microservice seeded successfully');
    }
}
