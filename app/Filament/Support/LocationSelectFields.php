<?php

namespace App\Filament\Support;

use App\Models\GeoCounty;
use App\Models\GeoLocality;
use App\Support\GeoLocations;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Reusable județ + localitate picker for the venue forms (admin /
 * marketplace / tenant panels), backed by the centralized geo dataset.
 *
 * Design — deliberately NON-DESTRUCTIVE:
 *   - These two selects are helpers only: `dehydrated(false)` means they
 *     are NEVER persisted as columns. The existing free-text `state` /
 *     `city` TextInputs remain the saved source of truth and the manual
 *     fallback for anything not in the list.
 *   - On form load they pre-select from the existing free-text values via
 *     diacritic-insensitive matching ("Bucuresti" → "București"), WITHOUT
 *     rewriting the text — the text is only updated when the operator
 *     actively picks an option.
 *   - On pick they write the canonical native-language name back into the
 *     text field (and lat/lng when empty), standardizing the data going
 *     forward.
 *
 * Splice the returned array into a form section, e.g.:
 *   ...LocationSelectFields::make(),
 *
 * Field/column names are configurable so it works regardless of whether a
 * panel labels its columns city/state or otherwise (defaults match the
 * Venue model: country/state/city/lat/lng).
 */
class LocationSelectFields
{
    public static function make(
        string $countryIso = 'RO',
        string $countyField = 'state',
        string $cityField = 'city',
        string $latField = 'lat',
        string $lngField = 'lng',
    ): array {
        return [
            Select::make('geo_county_id')
                ->label('Județ (selectează din listă)')
                ->helperText('Completează automat câmpul Județ de mai jos cu denumirea oficială.')
                ->options(fn () => GeoLocations::countyOptions($countryIso))
                ->searchable()
                ->preload()
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(function (Set $set, Get $get) use ($countryIso, $countyField) {
                    // Pre-select from the existing free-text county value.
                    $county = GeoLocations::matchCounty($get($countyField), $countryIso);
                    if ($county) {
                        $set('geo_county_id', $county->id);
                    }
                })
                ->afterStateUpdated(function ($state, Set $set) use ($countyField) {
                    // Reset the dependent city select; write canonical name.
                    $set('geo_city_id', null);
                    if ($state && ($county = GeoCounty::find($state))) {
                        $set($countyField, $county->name_native);
                    }
                })
                ->placeholder('— opțional —'),

            Select::make('geo_city_id')
                ->label('Localitate (selectează din listă)')
                ->helperText('Completează automat câmpul Oraș de mai jos cu denumirea oficială.')
                ->options(fn (Get $get) => GeoLocations::localityOptions($get('geo_county_id')))
                ->searchable()
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(function (Set $set, Get $get) use ($countryIso, $countyField, $cityField) {
                    $city = $get($cityField);
                    if (! $city) {
                        return;
                    }
                    // Resolve the county first (from the select if already set,
                    // otherwise from the free-text county value).
                    $countyId = $get('geo_county_id');
                    if (! $countyId) {
                        $countyId = GeoLocations::matchCounty($get($countyField), $countryIso)?->id;
                    }
                    $locality = GeoLocations::matchLocality($city, $countyId, $countryIso);
                    if ($locality) {
                        // Defensive: never overwrite an already-set
                        // geo_county_id with a mismatched match. matchLocality
                        // is now strict when a county is passed (it returns
                        // null on cross-county fallback), but keep this guard
                        // as belt-and-suspenders — a future refactor of the
                        // match logic shouldn't silently reintroduce the
                        // "Harghita → Argeș" bug.
                        if ($countyId && (int) $locality->county_id !== (int) $countyId) {
                            return;
                        }
                        $set('geo_county_id', $locality->county_id);
                        $set('geo_city_id', $locality->id);
                    }
                })
                ->afterStateUpdated(function ($state, Set $set, Get $get) use ($cityField, $latField, $lngField) {
                    if ($state && ($locality = GeoLocality::find($state))) {
                        $set($cityField, $locality->name_native);
                        // Fill coordinates only when empty — never clobber.
                        if (! $get($latField) && $locality->latitude) {
                            $set($latField, $locality->latitude);
                        }
                        if (! $get($lngField) && $locality->longitude) {
                            $set($lngField, $locality->longitude);
                        }
                    }
                })
                ->placeholder('— opțional —'),
        ];
    }
}
