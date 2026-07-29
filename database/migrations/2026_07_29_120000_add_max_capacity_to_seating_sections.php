<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General Access sections: a section that has no seats/rows but can have ticket
 * types assigned at an event and an (informational) maximum capacity.
 *
 * The section is flagged via the existing `section_type` column (value
 * 'general'); this migration only adds the capacity number. Nullable + no
 * default, so existing rows and all current flows are completely unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->unsignedInteger('max_capacity')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropColumn('max_capacity');
        });
    }
};
