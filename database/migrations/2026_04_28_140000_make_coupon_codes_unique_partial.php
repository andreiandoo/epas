<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Postgres doesn't honour Laravel soft-deletes in unique constraints.
        // Replace the full-table unique on (marketplace_client_id, code) with a
        // partial index that only covers live rows, so a code can be reused
        // after the previous holder is soft-deleted.
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE coupon_codes DROP CONSTRAINT IF EXISTS coupon_codes_marketplace_code_unique');
        DB::statement('DROP INDEX IF EXISTS coupon_codes_marketplace_code_unique');
        DB::statement('CREATE UNIQUE INDEX coupon_codes_marketplace_code_unique ON coupon_codes (marketplace_client_id, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS coupon_codes_marketplace_code_unique');
        DB::statement('CREATE UNIQUE INDEX coupon_codes_marketplace_code_unique ON coupon_codes (marketplace_client_id, code)');
    }
};
