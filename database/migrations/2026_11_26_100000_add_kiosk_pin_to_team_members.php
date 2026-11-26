<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // PIN pentru iesirea din aplicatie pe tabletele kiosk (hash, ca parola).
    public function up(): void
    {
        if (! Schema::hasColumn('marketplace_organizer_team_members', 'kiosk_pin')) {
            Schema::table('marketplace_organizer_team_members', function (Blueprint $table) {
                $table->string('kiosk_pin')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_organizer_team_members', 'kiosk_pin')) {
            Schema::table('marketplace_organizer_team_members', function (Blueprint $table) {
                $table->dropColumn('kiosk_pin');
            });
        }
    }
};
