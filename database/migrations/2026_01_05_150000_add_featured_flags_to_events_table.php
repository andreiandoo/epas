<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columnsToAdd = [];

        if (!Schema::hasColumn('events', 'is_homepage_featured')) {
            $columnsToAdd[] = 'is_homepage_featured';
        }
        if (!Schema::hasColumn('events', 'is_general_featured')) {
            $columnsToAdd[] = 'is_general_featured';
        }
        if (!Schema::hasColumn('events', 'is_category_featured')) {
            $columnsToAdd[] = 'is_category_featured';
        }

        if (!empty($columnsToAdd)) {
            Schema::table('events', function (Blueprint $table) use ($columnsToAdd) {
                if (in_array('is_homepage_featured', $columnsToAdd)) {
                    $table->boolean('is_homepage_featured')->default(false)->after('is_promoted');
                }
                if (in_array('is_general_featured', $columnsToAdd)) {
                    $table->boolean('is_general_featured')->default(false)->after('is_homepage_featured');
                }
                if (in_array('is_category_featured', $columnsToAdd)) {
                    $table->boolean('is_category_featured')->default(false)->after('is_general_featured');
                }
            });
        }

        // Add indexes if they don't exist
        try {
            $indexNames = collect(DB::select("SHOW INDEX FROM events"))->pluck('Key_name')->unique()->toArray();

            Schema::table('events', function (Blueprint $table) use ($indexNames) {
                if (!in_array('events_mp_homepage_featured_idx', $indexNames) && Schema::hasColumn('events', 'is_homepage_featured')) {
                    $table->index(['marketplace_client_id', 'is_homepage_featured'], 'events_mp_homepage_featured_idx');
                }
                if (!in_array('events_mp_general_featured_idx', $indexNames) && Schema::hasColumn('events', 'is_general_featured')) {
                    $table->index(['marketplace_client_id', 'is_general_featured'], 'events_mp_general_featured_idx');
                }
                if (!in_array('events_mp_category_featured_idx', $indexNames) && Schema::hasColumn('events', 'is_category_featured')) {
                    $table->index(['marketplace_client_id', 'is_category_featured', 'marketplace_event_category_id'], 'events_mp_category_featured_idx');
                }
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            try {
                $table->dropIndex('events_mp_homepage_featured_idx');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('events_mp_general_featured_idx');
            } catch (\Exception $e) {}
            try {
                $table->dropIndex('events_mp_category_featured_idx');
            } catch (\Exception $e) {}

            $columnsToDrop = [];
            foreach (['is_homepage_featured', 'is_general_featured', 'is_category_featured'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
