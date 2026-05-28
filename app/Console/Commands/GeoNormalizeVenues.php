<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Support\GeoLocations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup of free-text `venues.city` / `venues.state` values
 * to their canonical native spelling from the centralized geo dataset.
 *
 * Defaults to DRY-RUN. Pass --apply to actually write.
 *
 * Examples:
 *   php artisan geo:normalize-venues                  # dry-run, all RO venues
 *   php artisan geo:normalize-venues --apply          # commit changes
 *   php artisan geo:normalize-venues --marketplace=2  # restrict to a tenant
 *   php artisan geo:normalize-venues --verbose        # list every change
 *
 * Safety:
 *   - Never blanks or deletes a value — only rewrites when a confident
 *     match exists. Anything else is reported as unmatched / ambiguous
 *     and left untouched.
 *   - Foreign venues (country other than empty/RO/Romania) are skipped.
 *   - Apply path runs inside a single DB transaction.
 */
class GeoNormalizeVenues extends Command
{
    protected $signature = 'geo:normalize-venues
        {--apply : Commit changes (default is dry-run only)}
        {--dry-run : Alias for the default dry-run mode (no-op flag, kept for explicitness)}
        {--country=RO : Country ISO2 to scope the run}
        {--marketplace= : Restrict to one marketplace_client_id}
        {--tenant= : Restrict to one tenant_id}
        {--verbose-changes : Print every change row instead of the first 50}';

    protected $description = 'Normalize venues.city / venues.state to canonical native names from the geo dataset (dry-run by default).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $country = strtoupper((string) $this->option('country')) ?: 'RO';
        $marketplaceId = $this->option('marketplace');
        $tenantId = $this->option('tenant');
        $verbose = (bool) $this->option('verbose-changes');

        $this->line('');
        $this->info($apply ? '=== APPLY MODE — changes will be committed ===' : '=== DRY-RUN — no writes ===');
        $this->line("country={$country}  marketplace=" . ($marketplaceId ?: '*') . '  tenant=' . ($tenantId ?: '*'));
        $this->line('');

        $query = Venue::query()
            ->where(function ($q) {
                $q->whereNotNull('city')->where('city', '!=', '')
                  ->orWhereNotNull('state')->where('state', '!=', '');
            });

        if ($marketplaceId) {
            $query->where('marketplace_client_id', (int) $marketplaceId);
        }
        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $stats = [
            'scanned' => 0,
            'skipped_foreign' => 0,
            'already_canonical' => 0,
            'rewrite_city' => 0,
            'rewrite_state' => 0,
            'fill_state' => 0,
            'unmatched_city' => 0,
            'unmatched_state' => 0,
            'ambiguous_city' => 0,
            'would_downgrade_city' => 0,
            'would_downgrade_state' => 0,
        ];
        $stats['skipped_stylistic_city'] = 0;
        $stats['skipped_stylistic_state'] = 0;
        $changes = [];
        $unmatchedCity = [];
        $unmatchedState = [];
        $ambiguousCity = [];
        $downgradeCity = [];
        $downgradeState = [];
        $stylisticCity = [];
        $stylisticState = [];

        $countryFoldKeys = ['', strtolower($country), 'romania', 'românia'];

        $action = function (Venue $venue) use ($country, &$stats, &$changes, &$unmatchedCity, &$unmatchedState, &$ambiguousCity, &$downgradeCity, &$downgradeState, &$stylisticCity, &$stylisticState, $countryFoldKeys, $apply, $verbose) {
            $stats['scanned']++;

            // Foreign-country guard — only normalize when the country field
            // is empty or folds to the target country.
            $countryFold = GeoLocations::fold($venue->country);
            if (! in_array($countryFold, $countryFoldKeys, true)) {
                $stats['skipped_foreign']++;
                return;
            }

            $newCity = null;
            $newState = null;
            $reasons = [];

            // Step 1 — resolve county from state text.
            $county = $venue->state ? GeoLocations::matchCounty($venue->state, $country) : null;
            if ($venue->state && $county && $county->name_native !== $venue->state) {
                // Stylistic guard: skip when the only difference is
                // hyphen / space / case ("Bolintin-Vale" vs "Bolintin
                // Vale"). The source data isn't authoritative on these
                // axes, so operator-typed values may be more correct.
                if (GeoLocations::isStylisticVariant($venue->state, $county->name_native)) {
                    $stats['skipped_stylistic_state']++;
                    $stylisticState[] = ['id' => $venue->id, 'current' => $venue->state, 'canonical' => $county->name_native];
                } else {
                    // Safety guard: never replace a value with one that has
                    // FEWER diacritics — UNLESS the fold-keys differ, which
                    // means it's an alias (e.g. "Bucharest" → "București"
                    // would be safe; same-word downgrades like "Onești" →
                    // "Onesti" stay blocked).
                    $sameWord = GeoLocations::fold($county->name_native) === GeoLocations::fold($venue->state);
                    if ($sameWord && GeoLocations::countDiacritics($county->name_native) < GeoLocations::countDiacritics($venue->state)) {
                        $stats['would_downgrade_state']++;
                        $downgradeState[] = ['id' => $venue->id, 'current' => $venue->state, 'canonical' => $county->name_native];
                    } else {
                        $newState = $county->name_native;
                        $stats['rewrite_state']++;
                        $reasons[] = 'state→' . $county->name_native;
                    }
                }
            } elseif ($venue->state && ! $county) {
                $stats['unmatched_state']++;
                $unmatchedState[] = ['id' => $venue->id, 'state' => $venue->state];
            }

            // Step 2 — resolve locality from city text (scoped to county if resolved).
            if ($venue->city) {
                $loc = GeoLocations::matchLocality($venue->city, $county?->id, $country);
                if ($loc) {
                    // Ambiguity check: when no county was known, was the
                    // country-wide match unique by name? If not we abstain.
                    $isAmbiguous = false;
                    if (! $county) {
                        $foldKey = GeoLocations::fold($venue->city);
                        $countAcrossCountry = \App\Models\GeoLocality::query()
                            ->whereHas('country', fn ($q) => $q->where('iso2', $country))
                            ->where('name_ascii', $foldKey)
                            ->count();
                        $isAmbiguous = $countAcrossCountry > 1;
                    }

                    if ($isAmbiguous) {
                        $stats['ambiguous_city']++;
                        $ambiguousCity[] = ['id' => $venue->id, 'city' => $venue->city, 'state' => $venue->state];
                    } else {
                        if ($loc->name_native !== $venue->city) {
                            if (GeoLocations::isStylisticVariant($venue->city, $loc->name_native)) {
                                // Stylistic-only difference — keep operator
                                // value. See state branch above.
                                $stats['skipped_stylistic_city']++;
                                $stylisticCity[] = ['id' => $venue->id, 'current' => $venue->city, 'canonical' => $loc->name_native];
                            } else {
                                // Same safety guard for the city rewrite — only
                                // blocks when the fold-keys match (same word,
                                // fewer diacritics = real downgrade). Alias-
                                // driven rewrites with different folds (e.g.
                                // "București 5" → "Sector 5") pass through.
                                $sameWord = GeoLocations::fold($loc->name_native) === GeoLocations::fold($venue->city);
                                if ($sameWord && GeoLocations::countDiacritics($loc->name_native) < GeoLocations::countDiacritics($venue->city)) {
                                    $stats['would_downgrade_city']++;
                                    $downgradeCity[] = ['id' => $venue->id, 'current' => $venue->city, 'canonical' => $loc->name_native];
                                } else {
                                    $newCity = $loc->name_native;
                                    $stats['rewrite_city']++;
                                    $reasons[] = 'city→' . $loc->name_native;
                                }
                            }
                        }
                        // Backfill missing state from the matched locality's
                        // county, when state was empty.
                        if (! $venue->state && $loc->county_id) {
                            $cnty = \App\Models\GeoCounty::find($loc->county_id);
                            if ($cnty) {
                                $newState = $cnty->name_native;
                                $stats['fill_state']++;
                                $reasons[] = 'state(fill)→' . $cnty->name_native;
                            }
                        }
                    }
                } else {
                    $stats['unmatched_city']++;
                    $unmatchedCity[] = ['id' => $venue->id, 'city' => $venue->city, 'state' => $venue->state];
                }
            }

            if ($newCity === null && $newState === null) {
                if ($venue->city || $venue->state) {
                    $stats['already_canonical']++;
                }
                return;
            }

            $changes[] = [
                'id' => $venue->id,
                'old_city' => $venue->city,
                'new_city' => $newCity ?? $venue->city,
                'old_state' => $venue->state,
                'new_state' => $newState ?? $venue->state,
                'notes' => implode(', ', $reasons),
            ];

            if ($apply) {
                if ($newCity !== null) {
                    $venue->city = $newCity;
                }
                if ($newState !== null) {
                    $venue->state = $newState;
                }
                $venue->save();
            }
        };

        if ($apply) {
            DB::transaction(function () use ($query, $action) {
                $query->orderBy('id')->chunkById(200, function ($venues) use ($action) {
                    foreach ($venues as $v) {
                        $action($v);
                    }
                });
            });
        } else {
            $query->orderBy('id')->chunkById(200, function ($venues) use ($action) {
                foreach ($venues as $v) {
                    $action($v);
                }
            });
        }

        // --- Report ---------------------------------------------------------
        $this->line('');
        $this->info('Proposed changes:');
        $sample = $verbose ? $changes : array_slice($changes, 0, 50);
        if ($sample) {
            $this->table(
                ['id', 'city: current → canonical', 'state: current → canonical', 'notes'],
                array_map(fn ($r) => [
                    $r['id'],
                    ($r['old_city'] ?? '') . ($r['new_city'] !== $r['old_city'] ? "  →  {$r['new_city']}" : ''),
                    ($r['old_state'] ?? '') . ($r['new_state'] !== $r['old_state'] ? "  →  {$r['new_state']}" : ''),
                    $r['notes'],
                ], $sample),
            );
            if (! $verbose && count($changes) > count($sample)) {
                $this->line('  ... ' . (count($changes) - count($sample)) . ' more (re-run with --verbose-changes to list all)');
            }
        } else {
            $this->line('  (none)');
        }

        $this->line('');
        $this->info('Unmatched (will not be touched — verify manually):');
        $this->line('  unmatched city:      ' . count($unmatchedCity));
        if ($unmatchedCity) {
            $this->table(['id', 'city', 'state'], array_slice($unmatchedCity, 0, 20));
            if (count($unmatchedCity) > 20) {
                $this->line('  ... ' . (count($unmatchedCity) - 20) . ' more');
            }
        }
        $this->line('  unmatched state:     ' . count($unmatchedState));
        if ($unmatchedState) {
            $this->table(['id', 'state'], array_slice($unmatchedState, 0, 20));
            if (count($unmatchedState) > 20) {
                $this->line('  ... ' . (count($unmatchedState) - 20) . ' more');
            }
        }
        $this->line('  ambiguous city:      ' . count($ambiguousCity) . '  (same name in multiple counties, no state to disambiguate)');
        if ($ambiguousCity) {
            $this->table(['id', 'city', 'state'], array_slice($ambiguousCity, 0, 20));
            if (count($ambiguousCity) > 20) {
                $this->line('  ... ' . (count($ambiguousCity) - 20) . ' more');
            }
        }

        $this->line('');
        $this->info('Blocked by diacritic-safety guard (geo entry has fewer diacritics than the venue — needs a seeder fix, NOT rewritten):');
        $this->line('  would_downgrade city:  ' . count($downgradeCity));
        if ($downgradeCity) {
            $this->table(['id', 'current (kept)', 'geo canonical (skipped)'], array_slice($downgradeCity, 0, 20));
            if (count($downgradeCity) > 20) {
                $this->line('  ... ' . (count($downgradeCity) - 20) . ' more');
            }
        }
        $this->line('  would_downgrade state: ' . count($downgradeState));
        if ($downgradeState) {
            $this->table(['id', 'current (kept)', 'geo canonical (skipped)'], array_slice($downgradeState, 0, 20));
            if (count($downgradeState) > 20) {
                $this->line('  ... ' . (count($downgradeState) - 20) . ' more');
            }
        }

        $this->line('');
        $this->info('Skipped — stylistic only (hyphen / space / case differences, geo source not authoritative):');
        $this->line('  skipped_stylistic city:  ' . count($stylisticCity));
        if ($stylisticCity) {
            $this->table(['id', 'current (kept)', 'geo says (skipped)'], array_slice($stylisticCity, 0, 20));
            if (count($stylisticCity) > 20) {
                $this->line('  ... ' . (count($stylisticCity) - 20) . ' more');
            }
        }
        $this->line('  skipped_stylistic state: ' . count($stylisticState));
        if ($stylisticState) {
            $this->table(['id', 'current (kept)', 'geo says (skipped)'], array_slice($stylisticState, 0, 20));
            if (count($stylisticState) > 20) {
                $this->line('  ... ' . (count($stylisticState) - 20) . ' more');
            }
        }

        $this->line('');
        $this->info('Summary:');
        foreach ($stats as $k => $v) {
            $this->line('  ' . str_pad($k, 22) . $v);
        }

        $this->line('');
        if ($apply) {
            $this->info("Applied {$stats['rewrite_city']} city + {$stats['rewrite_state']} state rewrites and filled {$stats['fill_state']} states.");
        } else {
            $this->comment('Dry-run only — nothing changed. Re-run with --apply to commit.');
        }
        $this->line('');

        return self::SUCCESS;
    }
}
