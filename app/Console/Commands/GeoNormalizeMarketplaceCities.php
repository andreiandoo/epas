<?php

namespace App\Console\Commands;

use App\Models\MarketplaceCity;
use App\Models\MarketplaceClient;
use App\Support\GeoLocations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleanup pass for `marketplace_cities`:
 *
 *   1. Merge fold-key duplicates within a marketplace (e.g. "Călărași"
 *      seeded twice with the same spelling). Picks a winner, re-points
 *      every FK that references the losers (events / marketplace_events
 *      / activities — same set the existing
 *      `merge_duplicate_bucuresti_cities` migration handles), then
 *      deletes the losers.
 *   2. Rename a marketplace_city to the canonical native name from the
 *      central geo dataset when it differs (e.g. "Bucuresti" → "București"
 *      in one shot, future-proofing newly-typed venues). Same diacritic-
 *      safety guard as `geo:normalize-venues`: rename is blocked when the
 *      proposed value is the same word with fewer diacritics.
 *
 * Defaults to DRY-RUN (no writes without --apply). Apply path runs in a
 * single DB transaction so partial failures don't leave half-merged rows.
 *
 * Renaming targets the marketplace's RO row by default; pass --country
 * to override. Merging happens regardless of country (Moldovan / foreign
 * cities can still be duplicated within a marketplace and should be
 * deduplicated).
 */
class GeoNormalizeMarketplaceCities extends Command
{
    protected $signature = 'geo:normalize-marketplace-cities
        {--apply : Commit changes (default is dry-run only)}
        {--dry-run : Alias for the default dry-run mode (no-op flag, kept for explicitness)}
        {--country=RO : Country ISO2 to scope canonical renames}
        {--marketplace= : Restrict to one marketplace_client_id}
        {--verbose-changes : Print every change row instead of the first 50}';

    protected $description = 'Merge fold-duplicate marketplace_cities + rename to geo canonical native names (dry-run by default).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $country = strtoupper((string) $this->option('country')) ?: 'RO';
        $marketplaceId = $this->option('marketplace');
        $verbose = (bool) $this->option('verbose-changes');

        $this->line('');
        $this->info($apply ? '=== APPLY MODE — changes will be committed ===' : '=== DRY-RUN — no writes ===');
        $this->line("country={$country} (for canonical renames)  marketplace=" . ($marketplaceId ?: '*'));
        $this->line('');

        $marketplaces = MarketplaceClient::query()
            ->when($marketplaceId, fn ($q) => $q->where('id', (int) $marketplaceId))
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        if ($marketplaces->isEmpty()) {
            $this->warn('No matching marketplace_clients.');
            return self::SUCCESS;
        }

        $stats = [
            'mp_processed' => 0,
            'rows_scanned' => 0,
            'already_canonical' => 0,
            'rename_only' => 0,
            'merges' => 0,           // number of merge groups
            'rows_merged' => 0,      // number of loser rows deleted
            'events_repointed' => 0,
            'marketplace_events_repointed' => 0,
            'activities_repointed' => 0,
            'unmatched' => 0,
            'skipped_no_name' => 0,
            'skipped_stylistic' => 0,
            'would_downgrade' => 0,
        ];
        $renamePlans = [];
        $mergePlans = [];
        $downgradeRows = [];
        $unmatchedRows = [];
        $stylisticRows = [];

        foreach ($marketplaces as $mp) {
            $stats['mp_processed']++;
            $cities = MarketplaceCity::query()
                ->where('marketplace_client_id', $mp->id)
                ->get();

            $stats['rows_scanned'] += $cities->count();

            // Group by fold(primary name) within the marketplace.
            $groups = $cities->groupBy(function ($city) {
                $primary = self::primaryName($city);
                return $primary === null ? '__NO_NAME__' : GeoLocations::fold($primary);
            });

            foreach ($groups as $foldKey => $group) {
                if ($foldKey === '__NO_NAME__') {
                    $stats['skipped_no_name'] += $group->count();
                    continue;
                }

                // Look up canonical for the row's country (use the first
                // row's country as the group's country — they should all
                // match since they fold-equal).
                $cityCountry = strtoupper(trim((string) ($group->first()->country ?? '')));
                $canonical = null;
                if ($cityCountry === '' || $cityCountry === $country) {
                    $canonical = GeoLocations::matchLocality($foldKey, null, $country);
                }

                if ($group->count() > 1) {
                    // ---- MERGE GROUP ----
                    $winner = self::pickWinner($group, $canonical?->name_native);
                    $losers = $group->filter(fn ($c) => $c->id !== $winner->id)->values();

                    // Plan rename for winner if canonical differs.
                    $newName = null;
                    if ($canonical) {
                        $current = self::primaryName($winner);
                        if ($current !== $canonical->name_native) {
                            if (GeoLocations::isStylisticVariant($current, $canonical->name_native)) {
                                $stats['skipped_stylistic']++;
                                $stylisticRows[] = ['mp' => $mp->id, 'id' => $winner->id, 'current' => $current, 'canonical' => $canonical->name_native];
                            } else {
                                $sameWord = GeoLocations::fold($canonical->name_native) === GeoLocations::fold($current);
                                $downgrade = $sameWord && GeoLocations::countDiacritics($canonical->name_native) < GeoLocations::countDiacritics($current);
                                if (! $downgrade) {
                                    $newName = $canonical->name_native;
                                } else {
                                    $stats['would_downgrade']++;
                                    $downgradeRows[] = ['mp' => $mp->id, 'id' => $winner->id, 'current' => $current, 'canonical' => $canonical->name_native];
                                }
                            }
                        }
                    }

                    $mergePlans[] = [
                        'mp' => $mp->id,
                        'winner_id' => $winner->id,
                        'winner_current_name' => self::primaryName($winner),
                        'winner_new_name' => $newName,
                        'loser_ids' => $losers->pluck('id')->all(),
                        'loser_names' => $losers->map(fn ($c) => self::primaryName($c) . '#' . $c->id)->all(),
                        'country' => $cityCountry,
                    ];
                    $stats['merges']++;
                    $stats['rows_merged'] += $losers->count();
                } else {
                    // ---- SINGLE ROW ----
                    $row = $group->first();
                    if (! $canonical) {
                        $stats['unmatched']++;
                        $unmatchedRows[] = ['mp' => $mp->id, 'id' => $row->id, 'name' => self::primaryName($row), 'country' => $cityCountry];
                        continue;
                    }
                    $current = self::primaryName($row);
                    if ($current === $canonical->name_native) {
                        $stats['already_canonical']++;
                        continue;
                    }
                    if (GeoLocations::isStylisticVariant($current, $canonical->name_native)) {
                        $stats['skipped_stylistic']++;
                        $stylisticRows[] = ['mp' => $mp->id, 'id' => $row->id, 'current' => $current, 'canonical' => $canonical->name_native];
                        continue;
                    }
                    $sameWord = GeoLocations::fold($canonical->name_native) === GeoLocations::fold($current);
                    if ($sameWord && GeoLocations::countDiacritics($canonical->name_native) < GeoLocations::countDiacritics($current)) {
                        $stats['would_downgrade']++;
                        $downgradeRows[] = ['mp' => $mp->id, 'id' => $row->id, 'current' => $current, 'canonical' => $canonical->name_native];
                        continue;
                    }
                    $renamePlans[] = [
                        'mp' => $mp->id,
                        'id' => $row->id,
                        'current' => $current,
                        'new' => $canonical->name_native,
                        'country' => $cityCountry,
                    ];
                    $stats['rename_only']++;
                }
            }
        }

        // ---- APPLY ----
        if ($apply) {
            DB::transaction(function () use ($mergePlans, $renamePlans, &$stats) {
                foreach ($mergePlans as $plan) {
                    $loserIds = $plan['loser_ids'];

                    // Re-point every FK that references marketplace_cities.id.
                    // Same order as the existing merge_duplicate_bucuresti_cities
                    // migration: child tables first.
                    $stats['marketplace_events_repointed'] += DB::table('marketplace_events')
                        ->whereIn('marketplace_city_id', $loserIds)
                        ->update(['marketplace_city_id' => $plan['winner_id']]);

                    $stats['events_repointed'] += DB::table('events')
                        ->whereIn('marketplace_city_id', $loserIds)
                        ->update(['marketplace_city_id' => $plan['winner_id']]);

                    $stats['activities_repointed'] += DB::table('activities')
                        ->whereIn('marketplace_city_id', $loserIds)
                        ->update(['marketplace_city_id' => $plan['winner_id']]);

                    // Optionally rename the winner.
                    if ($plan['winner_new_name'] !== null) {
                        self::renameRow($plan['winner_id'], $plan['winner_new_name']);
                    }

                    // Finally drop the losers — FKs are nullOnDelete so this is
                    // safe, but we've already re-pointed everything anyway.
                    DB::table('marketplace_cities')->whereIn('id', $loserIds)->delete();
                }

                foreach ($renamePlans as $plan) {
                    self::renameRow($plan['id'], $plan['new']);
                }
            });
        }

        // ---- REPORT ----
        $this->line('');
        $this->info('Renames (no merge):');
        $sample = $verbose ? $renamePlans : array_slice($renamePlans, 0, 50);
        if ($sample) {
            $this->table(
                ['mp', 'id', 'current', '→', 'canonical', 'country'],
                array_map(fn ($r) => [$r['mp'], $r['id'], $r['current'], '→', $r['new'], $r['country']], $sample),
            );
            if (! $verbose && count($renamePlans) > count($sample)) {
                $this->line('  ... ' . (count($renamePlans) - count($sample)) . ' more (re-run with --verbose-changes to list all)');
            }
        } else {
            $this->line('  (none)');
        }

        $this->line('');
        $this->info('Merges:');
        $sampleM = $verbose ? $mergePlans : array_slice($mergePlans, 0, 50);
        if ($sampleM) {
            $this->table(
                ['mp', 'winner_id', 'winner name (after)', 'absorbed (losers)', 'country'],
                array_map(fn ($p) => [
                    $p['mp'],
                    $p['winner_id'],
                    ($p['winner_new_name'] ?? $p['winner_current_name'])
                        . ($p['winner_new_name'] !== null && $p['winner_new_name'] !== $p['winner_current_name'] ? "  (renamed from \"{$p['winner_current_name']}\")" : ''),
                    implode(', ', $p['loser_names']),
                    $p['country'],
                ], $sampleM),
            );
            if (! $verbose && count($mergePlans) > count($sampleM)) {
                $this->line('  ... ' . (count($mergePlans) - count($sampleM)) . ' more');
            }
        } else {
            $this->line('  (none)');
        }

        $this->line('');
        $this->info('Untouched:');
        $this->line('  unmatched in geo:        ' . count($unmatchedRows) . '  (custom / foreign / not yet in geo)');
        if ($unmatchedRows && $verbose) {
            $this->table(['mp', 'id', 'name', 'country'], array_slice($unmatchedRows, 0, 30));
        }
        $this->line('  blocked (would_downgrade): ' . count($downgradeRows));
        if ($downgradeRows) {
            $this->table(['mp', 'id', 'current (kept)', 'canonical (skipped)'], array_slice($downgradeRows, 0, 20));
        }
        $this->line('  blocked (stylistic only — hyphen/space/case): ' . count($stylisticRows));
        if ($stylisticRows) {
            $this->table(['mp', 'id', 'current (kept)', 'geo says (skipped)'], array_slice($stylisticRows, 0, 30));
            if (count($stylisticRows) > 30) {
                $this->line('  ... ' . (count($stylisticRows) - 30) . ' more');
            }
        }

        $this->line('');
        $this->info('Summary:');
        foreach ($stats as $k => $v) {
            $this->line('  ' . str_pad($k, 30) . $v);
        }

        $this->line('');
        if ($apply) {
            $this->info("Applied {$stats['rename_only']} renames and {$stats['merges']} merges ({$stats['rows_merged']} loser rows deleted).");
            $this->info("FK re-points: events={$stats['events_repointed']}, marketplace_events={$stats['marketplace_events_repointed']}, activities={$stats['activities_repointed']}.");
        } else {
            $this->comment('Dry-run only — nothing changed. Re-run with --apply to commit.');
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Return the row's primary display name — prefers `ro`, then `en`,
     * then the first available locale. Handles both JSON-array and raw
     * string storage (defensive for legacy rows).
     */
    private static function primaryName(MarketplaceCity $city): ?string
    {
        $raw = $city->getAttributes()['name'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                return trim($raw) ?: null;
            }
        }
        if (is_array($raw)) {
            $val = $raw['ro'] ?? $raw['en'] ?? reset($raw);
            return is_string($val) ? (trim($val) ?: null) : null;
        }
        return null;
    }

    /**
     * Within a fold-equal group, pick the winner: a row whose primary
     * name exactly matches the canonical wins, otherwise the row with
     * the most diacritics, otherwise the row with the lowest id.
     */
    private static function pickWinner($group, ?string $canonical)
    {
        $rows = $group->values();

        if ($canonical !== null) {
            foreach ($rows as $r) {
                if (self::primaryName($r) === $canonical) {
                    return $r;
                }
            }
        }

        // Most diacritics, tie-break by lowest id.
        return $rows->sort(function ($a, $b) {
            $da = GeoLocations::countDiacritics(self::primaryName($a) ?? '');
            $db = GeoLocations::countDiacritics(self::primaryName($b) ?? '');
            if ($da !== $db) {
                return $db <=> $da; // more diacritics first
            }
            return $a->id <=> $b->id;
        })->first();
    }

    /**
     * Update the row's `name` JSON: set `ro` (and `en` when it currently
     * equals `ro`, which means it's an auto-create echo of the Romanian
     * name rather than a real translation) to the canonical value. Other
     * locales are preserved as-is.
     */
    private static function renameRow(int $id, string $newName): void
    {
        $row = DB::table('marketplace_cities')->where('id', $id)->first();
        if (! $row) {
            return;
        }
        $current = $row->name;
        $name = is_string($current) ? (json_decode($current, true) ?: []) : (is_array($current) ? $current : []);
        if (! is_array($name)) {
            $name = [];
        }
        $oldRo = $name['ro'] ?? null;
        $name['ro'] = $newName;
        // Only touch `en` if it duplicated `ro` (auto-create signature);
        // a genuine English translation stays put.
        if (isset($name['en']) && $name['en'] === $oldRo) {
            $name['en'] = $newName;
        }
        DB::table('marketplace_cities')->where('id', $id)->update([
            'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
}
