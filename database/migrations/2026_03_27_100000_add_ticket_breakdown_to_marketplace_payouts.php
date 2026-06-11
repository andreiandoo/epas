<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_payouts') && !Schema::hasColumn('marketplace_payouts', 'ticket_breakdown')) {
            Schema::table('marketplace_payouts', function (Blueprint $table) {
                $table->json('ticket_breakdown')->nullable()->after('admin_notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_payouts') && Schema::hasColumn('marketplace_payouts', 'ticket_breakdown')) {
            Schema::table('marketplace_payouts', function (Blueprint $table) {
                $table->dropColumn('ticket_breakdown');
            });
        }
    }
};
