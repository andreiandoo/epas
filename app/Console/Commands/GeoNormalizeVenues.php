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
        ];
        $changes = [];
        $unmatchedCity = [];
        $unmatchedState = [];
        $ambiguousCity = [];

        $countryFoldKeys = ['', strtolower($country), 'romania', 'românia'];

        $action = function (Venue $venue) use ($country, &$stats, &$changes, &$unmatchedCity, &$unmatchedState, &$ambiguousCity, $countryFoldKeys, $apply, $verbose) {
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
                $newState = $county->name_native;
                $stats['rewrite_state']++;
                $reasons[] = 'state→' . $county->name_native;
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
                            $newCity = $loc->name_native;
                            $stats['rewrite_city']++;
                            $reasons[] = 'city→' . $loc->name_native;
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
