<?php

namespace Database\Seeders;

use App\Models\SupportDepartment;
use App\Models\SupportProblemType;
use Illuminate\Database\Seeder;

class AmbiletSupportTaxonomySeeder extends Seeder
{
    /** Ambilet marketplace_client_id (matches AmbiletKnowledgeBaseSeeder). */
    private int $marketplaceClientId = 1;

    public function run(): void
    {
        $this->command->info('Seeding Ambilet support departments + problem types...');

        // Disable global scope for seeding (no request context).
        $created = 0;

        foreach ($this->definitions() as $deptDef) {
            /** @var SupportDepartment $dept */
            $dept = SupportDepartment::withoutGlobalScopes()->updateOrCreate(
                [
                    'marketplace_client_id' => $this->marketplaceClientId,
                    'slug' => $deptDef['slug'],
                ],
                [
                    'name' => $deptDef['name'],
                    'description' => $deptDef['description'] ?? null,
                    'notify_emails' => $deptDef['notify_emails'] ?? [],
                    'sort_order' => $deptDef['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($deptDef['problem_types'] as $idx => $ptDef) {
                SupportProblemType::withoutGlobalScopes()->updateOrCreate(
                    [
                        'support_department_id' => $dept->id,
                        'slug' => $ptDef['slug'],
                    ],
                    [
                        'marketplace_client_id' => $this->marketplaceClientId,
                        'name' => $ptDef['name'],
                        'description' => $ptDef['description'] ?? null,
                        'required_fields' => $ptDef['required_fields'] ?? [],
                        'allowed_opener_types' => $ptDef['allowed_opener_types'] ?? ['organizer', 'customer'],
                        'sort_order' => $idx + 1,
                        'is_active' => true,
                    ]
                );
                $created++;
            }
        }

        $this->command->info("✓ Seeded support taxonomy ({$created} problem types).");
    }

    /**
     * @return list<array{slug:string,name:array,description?:array,notify_emails?:array,sort_order:int,problem_types:list<array<string,mixed>>}>
     */
    private function definitions(): array
    {
        return [
            [
                'slug' => 'tehnic',
                'name' => ['ro' => 'Tehnic', 'en' => 'Technical'],
                'description' => [
                    'ro' => 'Probleme tehnice cu platforma, module sau pagini specifice',
                    'en' => 'Technical issues with the platform, modules or specific pages',
                ],
                'notify_emails' => [],
                'sort_order' => 1,
                'problem_types' => [
                    [
                        'slug' => 'problema-modul',
                        'name' => ['ro' => 'Problemă cu un modul', 'en' => 'Issue with a module'],
                        'required_fields' => ['module_name', 'url'],
                    ],
                    [
                        'slug' => 'problema-pagina',
                        'name' => ['ro' => 'Problemă pe o pagină', 'en' => 'Page-specific issue'],
                        'required_fields' => ['url'],
                    ],
                    [
                        'slug' => 'bug-eroare',
                        'name' => ['ro' => 'Bug / eroare aplicație', 'en' => 'Bug / application error'],
                        'required_fields' => [],
                    ],
                    [
                        'slug' => 'cerere-functionalitate',
                        'name' => ['ro' => 'Cerere de funcționalitate', 'en' => 'Feature request'],
                        'required_fields' => [],
                        'allowed_opener_types' => ['organizer'],
                    ],
                ],
            ],
            [
                'slug' => 'financiar-decont',
                'name' => ['ro' => 'Financiar / Decont', 'en' => 'Finance / Settlements'],
                'description' => [
                    'ro' => 'Întrebări legate de deconturi, plăți, facturi și încasări',
                    'en' => 'Questions about settlements, payments, invoices and payouts',
                ],
                'notify_emails' => [],
                'sort_order' => 2,
                'problem_types' => [
                    [
                        'slug' => 'problema-decont',
                        'name' => ['ro' => 'Problemă cu un decont', 'en' => 'Issue with a settlement'],
                        'required_fields' => ['invoice_series', 'invoice_number'],
                        'allowed_opener_types' => ['organizer'],
                    ],
                    [
                        'slug' => 'lipsa-decont',
                        'name' => ['ro' => 'Lipsă decont', 'en' => 'Missing settlement'],
                        'required_fields' => ['event_id'],
                        'allowed_opener_types' => ['organizer'],
                    ],
                    [
                        'slug' => 'suma-gresita-decont',
                        'name' => ['ro' => 'Sumă greșită pe decont', 'en' => 'Wrong amount on settlement'],
                        'required_fields' => ['invoice_series', 'invoice_number'],
                        'allowed_opener_types' => ['organizer'],
                    ],
                    [
                        'slug' => 'intarziere-plata',
                        'name' => ['ro' => 'Întârziere plată / payout', 'en' => 'Delayed payment / payout'],
                        'required_fields' => ['event_id'],
                        'allowed_opener_types' => ['organizer'],
                    ],
                ],
            ],
            [
                'slug' => 'comercial',
                'name' => ['ro' => 'Comercial', 'en' => 'Commercial'],
                'description' => [
                    'ro' => 'Solicitări comerciale, contract, comision, microservicii',
                    'en' => 'Commercial requests, contract, commission, microservices',
                ],
                'sort_order' => 3,
                'problem_types' => [
                    [
                        'slug' => 'cerere-microservicii',
                        'name' => ['ro' => 'Cerere ofertă microservicii', 'en' => 'Microservices quote request'],
                        'required_fields' => [],
                        'allowed_opener_types' => ['organizer'],
                    ],
                    [
                        'slug' => 'modificare-contract',
                        'name' => ['ro' => 'Modificare contract / comision', 'en' => 'Contract / commission change'],
                        'required_fields' => [],
                        'allowed_opener_types' => ['organizer'],
                    ],
                ],
            ],
            [
                'slug' => 'conturi',
                'name' => ['ro' => 'Conturi', 'en' => 'Accounts'],
                'description' => [
                    'ro' => 'Probleme cu accesul la cont, parolă, date de contact',
                    'en' => 'Account access, password, contact details',
                ],
                'sort_order' => 4,
                'problem_types' => [
                    [
                        'slug' => 'resetare-parola',
                        'name' => ['ro' => 'Resetare parolă', 'en' => 'Password reset'],
                        'required_fields' => [],
                    ],
                    [
                        'slug' => 'acces-blocat',
                        'name' => ['ro' => 'Acces blocat / pierdut', 'en' => 'Locked out / lost access'],
                        'required_fields' => [],
                    ],
                    [
                        'slug' => 'schimbare-date-contact',
                        'name' => ['ro' => 'Schimbare email / telefon', 'en' => 'Change email / phone'],
                        'required_fields' => [],
                    ],
                ],
            ],
            [
                'slug' => 'altele',
                'name' => ['ro' => 'Altele', 'en' => 'Other'],
                'description' => [
                    'ro' => 'Sugestii, feedback sau orice altceva nu se încadrează în categoriile de mai sus',
                    'en' => 'Suggestions, feedback or anything that does not fit the above categories',
                ],
                'sort_order' => 99,
                'problem_types' => [
                    [
                        'slug' => 'sugestie',
                        'name' => ['ro' => 'Sugestie', 'en' => 'Suggestion'],
                        'required_fields' => [],
                    ],
                    [
                        'slug' => 'alta',
                        'name' => ['ro' => 'Altă problemă', 'en' => 'Other issue'],
                        'required_fields' => [],
                    ],
                ],
            ],
        ];
    }
}
