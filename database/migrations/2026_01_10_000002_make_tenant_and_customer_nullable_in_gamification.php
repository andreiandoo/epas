<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'customer_points' => ['tenant_id', 'customer_id'],
            'points_transactions' => ['tenant_id', 'customer_id'],
            'customer_experience' => ['customer_id'],
            'experience_transactions' => ['customer_id'],
            'customer_badges' => ['customer_id'],
            'reward_redemptions' => ['customer_id'],
        ];

        foreach ($tables as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) continue;
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" DROP NOT NULL");
                } else {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NULL");
                }
            }
        }
    }

    public function down(): void
    {
        // Note: Rolling back would require setting default values for tenant_id/customer_id
        // which is not always possible if marketplace data exists.
    }
};
