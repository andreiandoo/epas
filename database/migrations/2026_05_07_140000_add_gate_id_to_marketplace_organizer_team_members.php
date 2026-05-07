<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizer_team_members', function (Blueprint $table) {
            $table->unsignedBigInteger('gate_id')->nullable()->after('permissions');
            $table->index(['marketplace_organizer_id', 'gate_id'], 'mp_org_team_gate_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizer_team_members', function (Blueprint $table) {
            $table->dropIndex('mp_org_team_gate_idx');
            $table->dropColumn('gate_id');
        });
    }
};
