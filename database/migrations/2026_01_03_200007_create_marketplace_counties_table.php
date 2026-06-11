<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_counties')) {
            return;
        }

        Schema::create('marketplace_counties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()
                ->constrained('marketplace_regions')->nullOnDelete();
            $table->json('name'); // Translatable
            $table->string('slug');
            $table->string('code', 2); // e.g., 'CJ' for Cluj, 'TM' for Timiș
            $table->string('country', 2)->default('RO');
            $table->json('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('city_count')->default(0);
            $table->integer('event_count')->default(0);
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'slug'], 'mp_counties_client_slug_unique');
            $table->unique(['marketplace_client_id', 'code'], 'mp_counties_client_code_unique');
            $table->index(['marketplace_client_id', 'region_id'], 'mp_counties_region_idx');
            $table->index(['marketplace_client_id', 'is_visible', 'sort_order'], 'mp_counties_visible_idx');
        });

        // Add county_id to marketplace_cities
        if (!Schema::hasColumn('marketplace_cities', 'county_id')) {
            Schema::table('marketplace_cities', function (Blueprint $table) {
                $table->foreignId('county_id')->nullable()->after('region_id')
                    ->constrained('marketplace_counties')->nullOnDelete();
                $table->index(['marketplace_client_id', 'county_id'], 'mp_cities_county_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('marketplace_cities', 'county_id')) {
            Schema::table('marketplace_cities', function (Blueprint $table) {
                $table->dropIndex('mp_cities_county_idx');
                $table->dropForeign(['county_id']);
                $table->dropColumn('county_id');
            });
        }

        Schema::dropIfExists('marketplace_counties');
    }
};
