<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find all marketplace_cities that look like "Bucuresti" (with/without diacritics, leading spaces)
        $duplicates = DB::table('marketplace_cities')
            ->whereRaw("LOWER(TRIM(REPLACE(REPLACE(name::text, 'ş', 's'), 'ș', 's'))) LIKE '%bucuresti%'")
            ->orWhereRaw("LOWER(TRIM(slug)) LIKE '%bucuresti%'")
            ->get();

        if ($duplicates->count() <= 1) {
            return; // No duplicates to merge
        }

        // Pick the canonical city (prefer the one with proper diacritics or most events)
        $canonical = $duplicates->sortByDesc(function ($city) {
            $eventCount = DB::table('events')->where('marketplace_city_id', $city->id)->count();
            return $eventCount;
        })->first();

        $duplicateIds = $duplicates->pluck('id')->reject(fn ($id) => $id === $canonical->id)->toArray();

        if (empty($duplicateIds)) {
            return;
        }

        // Move all events from duplicates to canonical
        DB::table('events')
            ->whereIn('marketplace_city_id', $duplicateIds)
            ->update(['marketplace_city_id' => $canonical->id]);

        // Delete duplicate city records
        DB::table('marketplace_cities')
            ->whereIn('id', $duplicateIds)
            ->delete();

        // Also clean up venue city names — trim leading/trailing spaces
        DB::table('venues')
            ->whereRaw("city LIKE ' %' OR city LIKE '% '")
            ->update(['city' => DB::raw("TRIM(city)")]);
    }

    public function down(): void
    {
        // Cannot reverse merge
    }
};
