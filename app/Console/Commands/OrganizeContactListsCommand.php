<?php

namespace App\Console\Commands;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceContactList;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Organizes contact list members into proper lists:
 * - Customers with accepts_marketing → "Clienți și Abonați"
 * - Customers without accepts_marketing → "Clienți"
 * - Organizers → "Organizatori" (remain)
 * - Venue partner emails → "Locații"
 *
 * Usage: php artisan contacts:organize {marketplace_client_id}
 */
class OrganizeContactListsCommand extends Command
{
    protected $signature = 'contacts:organize {marketplace_client_id}';
    protected $description = 'Organize contact list members into proper categorized lists';

    public function handle(): int
    {
        $marketplaceId = (int) $this->argument('marketplace_client_id');
        $marketplace = MarketplaceClient::find($marketplaceId);

        if (!$marketplace) {
            $this->error("Marketplace client #{$marketplaceId} not found.");
            return 1;
        }

        $this->info("Organizing contacts for: {$marketplace->name}");

        // 1. Ensure the 4 target lists exist
        $lists = $this->ensureLists($marketplaceId);

        // 2. Get all organizer emails for this marketplace (to identify them)
        $organizerEmails = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()
            ->toArray();
        $this->info("  Found " . count($organizerEmails) . " organizer emails");

        // 3. Process all customers for this marketplace
        $totalCustomers = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)->count();
        $this->info("  Processing {$totalCustomers} customers...");

        $clientiAbonati = 0;
        $clienti = 0;
        $organizatori = 0;

        MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->chunkById(500, function ($customers) use ($lists, $organizerEmails, &$clientiAbonati, &$clienti, &$organizatori) {
                $batchAbonati = [];
                $batchClienti = [];
                $batchOrganizatori = [];
                $now = now();

                foreach ($customers as $customer) {
                    $email = strtolower(trim($customer->email ?? ''));

                    // Check if this customer is actually an organizer
                    if (in_array($email, $organizerEmails)) {
                        $batchOrganizatori[$customer->id] = [
                            'status' => 'subscribed',
                            'subscribed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $organizatori++;
                    } elseif ($customer->accepts_marketing) {
                        $batchAbonati[$customer->id] = [
                            'status' => 'subscribed',
                            'subscribed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $clientiAbonati++;
                    } else {
                        $batchClienti[$customer->id] = [
                            'status' => 'subscribed',
                            'subscribed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $clienti++;
                    }
                }

                // Sync without detaching (additive)
                if (!empty($batchAbonati)) {
                    $lists['clienti_abonati']->subscribers()->syncWithoutDetaching($batchAbonati);
                }
                if (!empty($batchClienti)) {
                    $lists['clienti']->subscribers()->syncWithoutDetaching($batchClienti);
                }
                if (!empty($batchOrganizatori)) {
                    $lists['organizatori']->subscribers()->syncWithoutDetaching($batchOrganizatori);
                }
            });

        $this->info("  → Clienți și Abonați: {$clientiAbonati}");
        $this->info("  → Clienți: {$clienti}");
        $this->info("  → Organizatori: {$organizatori}");

        // 4. Create venue contacts
        $venueCount = $this->createVenueContacts($marketplaceId, $lists['locatii']);
        $this->info("  → Locații: {$venueCount}");

        // 5. Update subscriber counts
        foreach ($lists as $list) {
            $list->update([
                'subscriber_count' => $list->activeSubscribers()->count(),
            ]);
        }

        // 6. Remove members from "Organizatori" list that are now in proper lists
        // (Only keep actual organizer emails in the Organizatori list)
        $organizatoriList = $lists['organizatori'];
        $nonOrganizerIds = $organizatoriList->subscribers()
            ->get()
            ->filter(function ($customer) use ($organizerEmails) {
                return !in_array(strtolower(trim($customer->email ?? '')), $organizerEmails);
            })
            ->pluck('id')
            ->toArray();

        if (!empty($nonOrganizerIds)) {
            $organizatoriList->subscribers()->detach($nonOrganizerIds);
            $this->info("  → Removed " . count($nonOrganizerIds) . " non-organizers from Organizatori list");
            $organizatoriList->update([
                'subscriber_count' => $organizatoriList->activeSubscribers()->count(),
            ]);
        }

        $this->info("\nDone!");
        return 0;
    }

    protected function ensureLists(int $marketplaceId): array
    {
        $listDefs = [
            'clienti_abonati' => [
                'name' => 'Clienți și Abonați',
                'description' => 'Clienți care au acceptat comunicări de marketing',
            ],
            'clienti' => [
                'name' => 'Clienți',
                'description' => 'Clienți fără opțiune de marketing',
            ],
            'organizatori' => [
                'name' => 'Organizatori',
                'description' => 'Organizatorii de evenimente parteneri',
            ],
            'locatii' => [
                'name' => 'Locații',
                'description' => 'Locațiile partenere și adresele lor de contact',
            ],
        ];

        $lists = [];
        foreach ($listDefs as $key => $def) {
            $lists[$key] = MarketplaceContactList::firstOrCreate(
                [
                    'marketplace_client_id' => $marketplaceId,
                    'name' => $def['name'],
                ],
                [
                    'description' => $def['description'],
                    'list_type' => 'manual',
                    'is_active' => true,
                    'is_default' => true,
                    'subscriber_count' => 0,
                ]
            );
        }

        return $lists;
    }

    protected function createVenueContacts(int $marketplaceId, MarketplaceContactList $list): int
    {
        // Get all partner venues for this marketplace
        $venues = Venue::where(function ($q) use ($marketplaceId) {
            $q->where('marketplace_client_id', $marketplaceId)
                ->orWhereHas('marketplaceClients', fn ($q2) => $q2->where('marketplace_client_id', $marketplaceId));
        })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        $count = 0;
        $now = now();

        foreach ($venues as $venue) {
            $emails = array_filter([
                $venue->email,
                $venue->email2 ?? null,
            ]);

            foreach ($emails as $email) {
                $email = strtolower(trim($email));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                // Find or create customer record for this venue email
                $customer = MarketplaceCustomer::firstOrCreate(
                    [
                        'marketplace_client_id' => $marketplaceId,
                        'email' => $email,
                    ],
                    [
                        'first_name' => is_array($venue->name)
                            ? ($venue->name['ro'] ?? $venue->name['en'] ?? reset($venue->name) ?? 'Locație')
                            : ($venue->name ?? 'Locație'),
                        'last_name' => '',
                        'accepts_marketing' => false,
                        'status' => 'active',
                    ]
                );

                $list->subscribers()->syncWithoutDetaching([
                    $customer->id => [
                        'status' => 'subscribed',
                        'subscribed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);
                $count++;
            }
        }

        return $count;
    }
}
