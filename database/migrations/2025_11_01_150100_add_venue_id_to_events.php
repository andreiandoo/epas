<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'venue_id')) {
                $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete()->after('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'venue_id')) {
                $table->dropConstrainedForeignId('venue_id');
            }
        });
    }
};
