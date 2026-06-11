<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Severity (Monolog level numeric: 100-DEBUG ... 600-EMERGENCY)
            $table->unsignedSmallInteger('level');
            $table->string('level_name', 20);

            // Routing labels
            $table->string('channel', 50)->nullable();
            $table->string('source', 50)->index();
            $table->string('category', 40);
            $table->string('subcategory', 60)->nullable();

            // Payload
            $table->text('message');
            $table->char('fingerprint', 40);

            // Exception detail (when applicable)
            $table->string('exception_class', 255)->nullable();
            $table->string('exception_file', 500)->nullable();
            $table->unsignedInteger('exception_line')->nullable();
            $table->text('stack_trace')->nullable();

            // Full Monolog context (jsonb on Postgres, json on others)
            $table->json('context')->nullable();

            // Request context (best-effort enrichment from current request)
            $table->string('request_url', 2048)->nullable();
            $table->string('request_method', 8)->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('request_user_agent')->nullable();
            $table->string('request_user_type', 20)->nullable();
            $table->unsignedBigInteger('request_user_id')->nullable();

            // Tenancy (when resolvable from context)
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('marketplace_client_id')->nullable();

            // Acknowledgement workflow
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->text('acknowledged_note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes — main listing + filtered listings + grouping
            $table->index('created_at', 'system_errors_created_at_idx');
            $table->index(['category', 'created_at'], 'system_errors_category_created_at_idx');
            $table->index(['level', 'created_at'], 'system_errors_level_created_at_idx');
            $table->index(['fingerprint', 'created_at'], 'system_errors_fingerprint_created_at_idx');
            $table->index(['channel', 'created_at'], 'system_errors_channel_created_at_idx');
            $table->index(['exception_class'], 'system_errors_exception_class_idx');
            $table->index(['marketplace_client_id', 'created_at'], 'system_errors_client_created_at_idx');
            $table->index(['tenant_id', 'created_at'], 'system_errors_tenant_created_at_idx');
            $table->index('subcategory', 'system_errors_subcategory_idx');
        });

        // Postgres: partial index for unacknowledged rows (the hot query path
        // for the admin dashboard) + jsonb cast for fast key extraction.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX system_errors_unacked_idx ON system_errors (created_at DESC) WHERE acknowledged_at IS NULL');
            DB::statement('CREATE INDEX system_errors_unacked_critical_idx ON system_errors (level, created_at DESC) WHERE acknowledged_at IS NULL AND level >= 500');
            DB::statement('ALTER TABLE system_errors ALTER COLUMN context TYPE jsonb USING context::jsonb');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_errors');
    }
};
