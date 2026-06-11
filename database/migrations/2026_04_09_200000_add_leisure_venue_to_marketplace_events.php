<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_events', 'display_template')) {
                $table->string('display_template', 50)->default('standard')->after('status');
            }
            if (!Schema::hasColumn('marketplace_events', 'venue_config')) {
                $table->jsonb('venue_config')->nullable()->after('display_template');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->dropColumn(['display_template', 'venue_config']);
        });
    }
};
