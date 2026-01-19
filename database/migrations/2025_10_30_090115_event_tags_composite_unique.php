<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('event_tags')) {
            try {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS event_tags_tenant_slug_unique ON event_tags(tenant_id, slug)');
            } catch (\Throwable $e) {
                // ignorăm dacă există deja ca constraint
            }
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS event_tags_tenant_slug_unique');
        } catch (\Throwable $e) {}
    }
};
