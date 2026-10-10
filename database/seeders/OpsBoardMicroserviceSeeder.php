<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the "Tablă operațiuni" microservice metadata.
 *
 * Microservice slug: `ops-board`.
 *
 * Activates the operations board in marketplace admin: per event, what is
 * still to be done (cerere vizare, decont, factură, impozit, PV distrugere),
 * computed from existing data — operators never move a card by hand.
 *
 * Activation is per marketplace via the `marketplace_client_microservices`
 * pivot — adding a row here only registers the module catalog entry.
 *
 * Idempotent: `updateOrInsert` keyed on slug; safe to re-run.
 */
class OpsBoardMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'ops-board'],
            [
                'name' => json_encode([
                    'en' => 'Operations Board',
                    'ro' => 'Tablă operațiuni',
                ], JSON_UNESCAPED_UNICODE),
                'description' => json_encode([
                    'en' => 'Weekly or monthly board of what is still to be done for each event: stamping request, settlement, invoice, show tax and ticket destruction report. Every state is computed from the existing records, including what is overdue from earlier periods.',
                    'ro' => 'Tablă săptămânală sau lunară cu ce mai e de făcut pentru fiecare eveniment: cerere de vizare, decont, factură, impozit pe spectacole și PV de distrugere bilete. Fiecare stare e calculată din datele existente, inclusiv restanțele din perioadele anterioare.',
                ], JSON_UNESCAPED_UNICODE),
                'short_description' => json_encode([
                    'en' => 'Per-event to-do board for settlements and fiscal documents',
                    'ro' => 'Tablă cu ce e de făcut per eveniment: deconturi și documente fiscale',
                ], JSON_UNESCAPED_UNICODE),
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Week and month views',
                        'One row per event, one cell per task',
                        'Deadlines computed per task type',
                        'Overdue items carried over from earlier periods',
                        'No manual card moves: states follow the records',
                    ],
                    'ro' => [
                        'Vedere pe săptămână și pe lună',
                        'Un rând per eveniment, o celulă per task',
                        'Scadențe calculate pe fiecare tip de task',
                        'Restanțele din urmă apar în perioada curentă',
                        'Fără mutări manuale: starea urmează datele',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'category' => 'operations',
                'is_active' => true,
                'metadata' => json_encode([
                    'gate_helper' => "MarketplaceClient::hasMicroservice('ops-board')",
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Ops Board microservice metadata seeded (slug: ops-board, is_active: true)');
        $this->command->line('  Activate per marketplace via /admin/microservices or the marketplace_client_microservices pivot.');
    }
}
