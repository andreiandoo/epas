<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'is_city_featured')) {
            Schema::table('events', function (Blueprint $table) {
                $table->boolean('is_city_featured')->default(false)->after('is_category_featured');
            });
        }

        try {
            $indexNames = collect(\DB::select('SHOW INDEX FROM events'))->pluck('Key_name')->unique()->toArray();
            if (! in_array('events_mp_city_featured_idx', $indexNames) && Schema::hasColumn('events', 'is_city_featured')) {
                Schema::table('events', function (Blueprint $table) {
                    $table->index(
                        ['marketplace_client_id', 'is_city_featured', 'marketplace_city_id'],
                        'events_mp_city_featured_idx'
                    );
                });
            }
        } catch (\Exception $e) {
            // Index may not work on all DB engines (e.g. SQLite)
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            try {
                $table->dropIndex('events_mp_city_featured_idx');
            } catch (\Exception $e) {
            }

            if (Schema::hasColumn('events', 'is_city_featured')) {
                $table->dropColumn('is_city_featured');
            }
        });
    }
};
