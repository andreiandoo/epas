<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('marketplace_payouts', 'decont_mentions')) {
            return;
        }

        Schema::table('marketplace_payouts', function (Blueprint $table) {
            // Text liber pus de operator pe decont, tipărit în PDF prin {{payout_mentions}}.
            $table->text('decont_mentions')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketplace_payouts', 'decont_mentions')) {
            Schema::table('marketplace_payouts', function (Blueprint $table) {
                $table->dropColumn('decont_mentions');
            });
        }
    }
};
