<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registers the "Discovery Module" microservice (slug: `discovery-module`) and
 * activates it for bilete.online (marketplace_client_id 3) so the GYG-style
 * discovery taxonomies (Interests, Traveler types, Attractions) show up
 * immediately in its admin.
 *
 * Gate helper: MarketplaceClient::hasMicroservice('discovery-module')
 * Idempotent: updateOrInsert on slug + on the activation pivot.
 *
 * Override the auto-activated client with DISCOVERY_CLIENT_ID.
 */
class DiscoveryModuleMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('microservices')->updateOrInsert(
            ['slug' => 'discovery-module'],
            [
                'name' => json_encode([
                    'en' => 'Discovery Module',
                    'ro' => 'Modul Descoperire',
                ], JSON_UNESCAPED_UNICODE),
                'description' => json_encode([
                    'en' => 'GetYourGuide-style discovery layer for the Activities module: Interests and Traveler-type taxonomies, Attractions (points of interest) with geo + types, and proximity (Nearby) surfaces. Powers faceted filters, badges, attraction sections on city pages, and "near me" rails.',
                    'ro' => 'Strat de descoperire în stil GetYourGuide peste modulul Activități: taxonomii Interese și Tipuri de călători, Atracții (puncte de interes) cu geo + tipuri, și proximitate (În apropiere). Alimentează filtrele facetate, badge-urile, secțiunile de atracții pe paginile de oraș și rândurile „aproape de mine".',
                ], JSON_UNESCAPED_UNICODE),
                'short_description' => json_encode([
                    'en' => 'Interests, traveler types, attractions + nearby for activities',
                    'ro' => 'Interese, tipuri de călători, atracții + nearby pentru activități',
                ], JSON_UNESCAPED_UNICODE),
                'price' => 0.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => json_encode([
                    'en' => [
                        'Interests taxonomy (mystery, adventure, culture, food, nature…)',
                        'Traveler types (couples, families, solo, groups, kids…)',
                        'Attractions (points of interest) with type + city + geo',
                        'Activity ↔ attraction linking',
                        'Nearby (Haversine) proximity rails on activity pages',
                        'Faceted filters by interest / traveler type on category pages',
                        'SEO-ready discovery badges on activity cards',
                    ],
                    'ro' => [
                        'Taxonomie interese (mister, aventură, cultură, gastronomie, natură…)',
                        'Tipuri de călători (cupluri, familii, solo, grupuri, copii…)',
                        'Atracții (puncte de interes) cu tip + oraș + geo',
                        'Legare activitate ↔ atracție',
                        'Rânduri „În apropiere" (Haversine) pe paginile de activitate',
                        'Filtre facetate după interes / tip de călător pe paginile de categorie',
                        'Badge-uri de descoperire SEO pe cardurile de activitate',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'category' => 'commerce',
                'is_active' => true,
                'metadata' => json_encode([
                    'requires' => ['activities-module'],
                    'database_tables' => [
                        'interests', 'traveler_types', 'activity_interest', 'activity_traveler_type',
                        'attraction_types', 'attractions', 'activity_attraction',
                        'activities.latitude', 'activities.longitude',
                    ],
                    'endpoints' => [
                        'GET /api/marketplace-client/activities (interests/traveler_types filters)',
                        'GET /api/marketplace-client/attractions',
                        'GET /api/marketplace-client/attractions/{slug}',
                    ],
                    'gate_helper' => "MarketplaceClient::hasMicroservice('discovery-module')",
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $microserviceId = (int) DB::table('microservices')->where('slug', 'discovery-module')->value('id');
        $clientId = (int) env('DISCOVERY_CLIENT_ID', 3);

        if ($microserviceId && $clientId && DB::table('marketplace_clients')->where('id', $clientId)->exists()) {
            // Activate for the target marketplace (status='active' is what
            // MarketplaceClient::hasMicroservice() checks).
            $existing = DB::table('marketplace_client_microservices')
                ->where('marketplace_client_id', $clientId)
                ->where('microservice_id', $microserviceId)
                ->first();

            $payload = [
                'status'       => 'active',
                'is_active'    => true,
                'activated_at' => now(),
                'updated_at'   => now(),
            ];

            if ($existing) {
                DB::table('marketplace_client_microservices')
                    ->where('id', $existing->id)
                    ->update($payload);
            } else {
                DB::table('marketplace_client_microservices')->insert(array_merge($payload, [
                    'marketplace_client_id' => $clientId,
                    'microservice_id'       => $microserviceId,
                    'created_at'            => now(),
                ]));
            }

            $this->command?->info("✓ Discovery Module activated for marketplace_client #{$clientId}.");
        } else {
            $this->command?->warn("Discovery Module registered, but client #{$clientId} not found — activate manually via /admin/microservices.");
        }
    }
}
