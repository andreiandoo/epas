<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two more narrowing filters on the recipient targeting:
 *
 *   - target_category_ids   JSON, nullable
 *       Restrict the buyer-filter to events whose
 *       marketplace_event_category_id is in this list (AND with city /
 *       organizer / artist if those are set).
 *
 *   - target_artist_ids     JSON, nullable
 *       Restrict to events that include at least one of these artists
 *       (event_artist pivot). Useful for tour-specific blasts: "everyone
 *       who bought a ticket at any event featuring artist X".
 *
 * Both intersect with the existing target_city_ids + target_organizer_ids
 * + target_event_ids resolver. Empty/null = ignored.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_newsletters', 'target_category_ids')) {
                $table->json('target_category_ids')->nullable()->after('target_city_ids');
            }
            if (!Schema::hasColumn('marketplace_newsletters', 'target_artist_ids')) {
                $table->json('target_artist_ids')->nullable()->after('target_category_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            foreach (['target_category_ids', 'target_artist_ids'] as $col) {
                if (Schema::hasColumn('marketplace_newsletters', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
