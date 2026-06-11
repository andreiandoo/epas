<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized geographic reference dataset (core, global — NOT per-marketplace).
 *
 * Native-language place names (RO: județe + localități incl. comune/sate),
 * extensible to other countries. This is the single canonical source for
 * any location selector going forward (venues now; events / artists /
 * customer addresses / onboarding later).
 *
 *   geo_countries  — one row per country (iso2 unique), native + EN names.
 *   geo_counties   — first-level subdivision (RO: județ). `code` = AB/B/CJ…
 *   geo_localities — settlement (RO: municipiu/oraș/comună/sat). `type` is
 *                    nullable for now (data lacks reliable classification);
 *                    reserved for future SIRUTA enrichment.
 *
 * `name_ascii` columns hold a lowercased, diacritic-folded form so a
 * free-text value like "Bucuresti" matches the canonical "București"
 * with a simple indexed equality lookup — used by the venue form helper
 * selects to pre-fill from existing free-text city/state values.
 *
 * Purely additive: no existing table or column is touched. Nothing depends
 * on these tables until the venue form helper (shipped alongside) reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso2', 2)->unique();          // RO
            $table->string('iso3', 3)->nullable();        // ROU
            $table->string('name_native');                // România
            $table->string('name_en')->nullable();        // Romania
            $table->string('phone_code', 8)->nullable();  // +40
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('geo_counties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('geo_countries')->cascadeOnDelete();
            $table->string('code', 8)->nullable();        // AB, B, CJ
            $table->string('name_native');                // Argeș
            $table->string('name_ascii')->index();        // arges (folded, for matching)
            $table->string('slug');                       // arges (url)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['country_id', 'slug']);
            $table->index(['country_id', 'code']);
        });

        Schema::create('geo_localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('geo_countries')->cascadeOnDelete();
            $table->foreignId('county_id')->constrained('geo_counties')->cascadeOnDelete();
            $table->string('name_native');                // Pitești
            $table->string('name_ascii')->index();        // pitesti (folded, for matching)
            $table->string('slug');                       // pitesti
            $table->string('type', 24)->nullable();       // municipiu|oras|comuna|sat (future)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['county_id', 'name_ascii']);
            $table->index(['country_id', 'name_ascii']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_localities');
        Schema::dropIfExists('geo_counties');
        Schema::dropIfExists('geo_countries');
    }
};
