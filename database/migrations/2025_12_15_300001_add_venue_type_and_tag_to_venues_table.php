<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            // Add venue_type_id if it doesn't exist
            if (!Schema::hasColumn('venues', 'venue_type_id')) {
                $table->foreignId('venue_type_id')->nullable()->after('tenant_id')->constrained('venue_types')->nullOnDelete();
            }

            // Add venue_tag if it doesn't exist (dropdown: historic, popular)
            if (!Schema::hasColumn('venues', 'venue_tag')) {
                $table->string('venue_tag')->nullable()->after('venue_type_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'venue_type_id')) {
                $table->dropConstrainedForeignId('venue_type_id');
            }
            if (Schema::hasColumn('venues', 'venue_tag')) {
                $table->dropColumn('venue_tag');
            }
        });
    }
};
