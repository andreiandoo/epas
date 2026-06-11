<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE "acc_connectors" DROP CONSTRAINT IF EXISTS acc_connectors_provider_check');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        // intentionally empty — we don't want to restore the old enum check
    }
};
