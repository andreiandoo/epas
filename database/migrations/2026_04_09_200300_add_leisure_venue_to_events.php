<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'display_template')) {
                $table->string('display_template', 50)->default('standard')->after('enable_ticket_perks');
            }
            if (!Schema::hasColumn('events', 'venue_config')) {
                $table->jsonb('venue_config')->nullable()->after('display_template');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['display_template', 'venue_config']);
        });
    }
};
