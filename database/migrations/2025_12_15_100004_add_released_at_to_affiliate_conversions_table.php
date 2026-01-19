<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->timestamp('released_at')->nullable()->after('status')
                ->comment('When the commission was released from hold period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_conversions', function (Blueprint $table) {
            $table->dropColumn('released_at');
        });
    }
};
