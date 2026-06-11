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
 * Organizes contact list members into proper lists.
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

        // 1. Ensure the 5 target lists exist
        $lists = $this->ensureLists($marketplaceId);

        // 2. Get all organizer emails
        $organizerEmails = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()
            ->toArray();
        $this->info("  Found " . count($organizerEmails) . " organizer emails");

        // 3. First, clear the Organizatori list completely (it had 76K wrong entries)
        $this->info("  Clearing old Organizatori list...");
        DB::table('marketplace_contact_list_members')
            ->where('list_id', $lists['organizatori']->id)
            ->delete();
        $this->info("  → Organizatori list cleared");

        // 4. Process all customers
        $totalCustomers = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)->count();
        $this->info("  Processing {$totalCustomers} customers...");

        $counters = ['clienti_abonati' => 0, 'abonati' => 0, 'clienti' => 0, 'organizatori' => 0];

        MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->select(['id', 'email', 'accepts_marketing'])
            ->chunkById(1000, function ($customers) use ($lists, $organizerEmails, &$counters) {
                $inserts = [
                    'clienti_abonati' => [],
                    'abonati' => [],
                    'clienti' => [],
                    'organizatori' => [],
                ];
                $now = now()->toDateTimeString();

                foreach ($customers as $customer) {
                    $email = strtolower(trim($customer->email ?? ''));

                    if (in_array($email, $organizerEmails)) {
                        $inserts['organizatori'][] = $customer->id;
                        $counters['organizatori']++;
                    } elseif ($customer->accepts_marketing) {
                        $inserts['clienti_abonati'][] = $customer->id;
                        $inserts['abonati'][] = $customer->id;
                        $counters['clienti_abonati']++;
                        $counters['abonati']++;
                    } else {
                        $inserts['clienti'][] = $customer->id;
                        $counters['clienti']++;
                    }
                }

                // Batch insert using raw DB to avoid placeholder limits
                foreach ($inserts as $listKey => $customerIds) {
                    if (empty($customerIds)) continue;

                    $listId = $lists[$listKey]->id;

                    // Insert in small batches of 100
                    foreach (array_chunk($customerIds, 100) as $chunk) {
                        $rows = array_map(fn ($cid) => [
                            'list_id' => $listId,
                            'marketplace_customer_id' => $cid,
                            'status' => 'subscribed',
                            'subscribed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $chunk);

                        DB::table('marketplace_contact_list_members')
                            ->insertOrIgnore($rows);
                    }
                }
            });

        $this->info("  → Clienți și Abonați: {$counters['clienti_abonati']}");
        $this->info("  → Abonați: {$counters['abonati']}");
        $this->info("  → Clienți: {$counters['clienti']}");
        $this->info("  → Organizatori (from customers): {$counters['organizatori']}");

        // 4b. Ensure ALL organizers are in the Organizatori list
        // Create customer records for organizers whose email doesn't exist in customers table
        $this->info("  Syncing remaining organizers...");
        $orgListId = $lists['organizatori']->id;
        $addedOrganizers = 0;

        MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'email', 'name', 'company_name'])
            ->chunkById(500, function ($organizers) use ($marketplaceId, $orgListId, &$addedOrganizers) {
                $now = now()->toDateTimeString();

                foreach ($organizers as $organizer) {
                    $email = strtolower(trim($organizer->email));
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                    $customer = MarketplaceCustomer::firstOrCreate(
                        [
                            'marketplace_client_id' => $marketplaceId,
                            'email' => $email,
                        ],
                        [
                            'first_name' => $organizer->name ?: ($organizer->company_name ?: 'Organizator'),
                            'last_name' => '',
                            'accepts_marketing' => false,
                            'status' => 'active',
                        ]
                    );

                    $inserted = DB::table('marketplace_contact_list_members')->insertOrIgnore([
                        'list_id' => $orgListId,
                        'marketplace_customer_id' => $customer->id,
                        'status' => 'subscribed',
                        'subscribed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted) {
                        $addedOrganizers++;
                    }
                }
            });

        $this->info("  → Organizatori (created/synced): {$addedOrganizers}");

        // 5. Create venue contacts
        $venueCount = $this->createVenueContacts($marketplaceId, $lists['locatii']);
        $this->info("  → Locații: {$venueCount}");

        // 6. Update subscriber counts
        foreach ($lists as $list) {
            $count = DB::table('marketplace_contact_list_members')
                ->where('list_id', $list->id)
                ->where('status', 'subscribed')
                ->count();
            $list->update(['subscriber_count' => $count]);
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
            'abonati' => [
                'name' => 'Abonați',
                'description' => 'Abonați la newsletter și comunicări de marketing',
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
        $venues = Venue::where(function ($q) use ($marketplaceId) {
            $q->where('marketplace_client_id', $marketplaceId)
                ->orWhereHas('marketplaceClients', fn ($q2) => $q2->where('marketplace_client_id', $marketplaceId));
        })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        $count = 0;
        $now = now()->toDateTimeString();
        $listId = $list->id;

        foreach ($venues as $venue) {
            $emails = array_filter([
                $venue->email,
                $venue->email2 ?? null,
            ]);

            foreach ($emails as $email) {
                $email = strtolower(trim($email));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

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

                DB::table('marketplace_contact_list_members')->insertOrIgnore([
                    'list_id' => $listId,
                    'marketplace_customer_id' => $customer->id,
                    'status' => 'subscribed',
                    'subscribed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
