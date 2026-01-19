<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make tenant_id and customer_id nullable in gamification tables
     * to support marketplace customers (who don't have tenant_id or customer_id)
     */
    public function up(): void
    {
        // Customer Points - make tenant_id and customer_id nullable
        if (Schema::hasTable('customer_points')) {
            // Use raw SQL for MySQL to alter columns
            DB::statement('ALTER TABLE `customer_points` MODIFY `tenant_id` BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE `customer_points` MODIFY `customer_id` BIGINT UNSIGNED NULL');
        }

        // Points Transactions
        if (Schema::hasTable('points_transactions')) {
            DB::statement('ALTER TABLE `points_transactions` MODIFY `tenant_id` BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE `points_transactions` MODIFY `customer_id` BIGINT UNSIGNED NULL');
        }

        // Customer Experience - customer_id
        if (Schema::hasTable('customer_experience') && Schema::hasColumn('customer_experience', 'customer_id')) {
            DB::statement('ALTER TABLE `customer_experience` MODIFY `customer_id` BIGINT UNSIGNED NULL');
        }

        // Experience Transactions - customer_id
        if (Schema::hasTable('experience_transactions') && Schema::hasColumn('experience_transactions', 'customer_id')) {
            DB::statement('ALTER TABLE `experience_transactions` MODIFY `customer_id` BIGINT UNSIGNED NULL');
        }

        // Customer Badges - customer_id
        if (Schema::hasTable('customer_badges') && Schema::hasColumn('customer_badges', 'customer_id')) {
            DB::statement('ALTER TABLE `customer_badges` MODIFY `customer_id` BIGINT UNSIGNED NULL');
        }

        // Reward Redemptions - customer_id
        if (Schema::hasTable('reward_redemptions') && Schema::hasColumn('reward_redemptions', 'customer_id')) {
            DB::statement('ALTER TABLE `reward_redemptions` MODIFY `customer_id` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Note: Rolling back would require setting default values for tenant_id/customer_id
        // which is not always possible if marketplace data exists.
    }
};
