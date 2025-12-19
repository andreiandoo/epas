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
        // Skip if columns already exist
        if (Schema::hasColumn('customers', 'points_balance')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('points_balance')->default(0)->after('meta');
            $table->unsignedInteger('points_earned')->default(0)->after('points_balance');
            $table->unsignedInteger('points_spent')->default(0)->after('points_earned');
            $table->string('referral_code', 20)->nullable()->unique()->after('points_spent');
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('customers')->nullOnDelete();

            // Indexes for quick lookups
            $table->index('points_balance');
            $table->index('referral_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropIndex(['points_balance']);
            $table->dropIndex(['referral_code']);
            $table->dropColumn([
                'points_balance',
                'points_earned',
                'points_spent',
                'referral_code',
                'referred_by',
            ]);
        });
    }
};
