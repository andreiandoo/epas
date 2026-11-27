<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Prezența operatorilor (ultima activitate în panou) și ce primește fiecare departament de suport.
    public function up(): void
    {
        if (Schema::hasTable('marketplace_admins') && ! Schema::hasColumn('marketplace_admins', 'last_seen_at')) {
            Schema::table('marketplace_admins', function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable();
            });
        }

        if (Schema::hasTable('support_departments') && ! Schema::hasColumn('support_departments', 'cockpit_scope')) {
            Schema::table('support_departments', function (Blueprint $table) {
                // {"categories": ["ticket", ...], "journal_types": ["ticket_sale", ...]}; gol = tot
                $table->json('cockpit_scope')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_admins', 'last_seen_at')) {
            Schema::table('marketplace_admins', fn (Blueprint $t) => $t->dropColumn('last_seen_at'));
        }
        if (Schema::hasColumn('support_departments', 'cockpit_scope')) {
            Schema::table('support_departments', fn (Blueprint $t) => $t->dropColumn('cockpit_scope'));
        }
    }
};
