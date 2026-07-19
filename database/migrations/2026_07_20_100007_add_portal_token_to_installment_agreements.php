<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unguessable per-agreement token for the customer portal + early payoff, so
 * those endpoints aren't reachable by iterating sequential ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('installment_agreements')) {
            return;
        }
        Schema::table('installment_agreements', function (Blueprint $table) {
            if (! Schema::hasColumn('installment_agreements', 'portal_token')) {
                $table->string('portal_token', 64)->nullable()->unique()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('installment_agreements', 'portal_token')) {
            Schema::table('installment_agreements', function (Blueprint $table) {
                $table->dropColumn('portal_token');
            });
        }
    }
};
