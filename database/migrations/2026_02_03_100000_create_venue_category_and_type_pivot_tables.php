<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create pivot tables for many-to-many venue categories and types.
     * Allows up to 3 categories and 5 types per venue.
     */
    public function up(): void
    {
        // Pivot: venues <-> venue_categories (max 3)
        Schema::create('venue_category_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['venue_id', 'venue_category_id']);
        });

        // Pivot: venues <-> venue_types (max 5)
        Schema::create('venue_type_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['venue_id', 'venue_type_id']);
        });

        // Migrate existing venue_type_id data to pivot table
        $venues = DB::table('venues')->whereNotNull('venue_type_id')->get(['id', 'venue_type_id']);
        foreach ($venues as $venue) {
            DB::table('venue_type_venue')->insert([
                'venue_id' => $venue->id,
                'venue_type_id' => $venue->venue_type_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Also add the category from the type
            $type = DB::table('venue_types')->where('id', $venue->venue_type_id)->first();
            if ($type && $type->venue_category_id) {
                DB::table('venue_category_venue')->insertOrIgnore([
                    'venue_id' => $venue->id,
                    'venue_category_id' => $type->venue_category_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_type_venue');
        Schema::dropIfExists('venue_category_venue');
    }
};
