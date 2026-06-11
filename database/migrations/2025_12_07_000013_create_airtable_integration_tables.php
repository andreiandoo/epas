<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Airtable connections (Personal Access Token or OAuth)
        if (Schema::hasTable('airtable_connections')) {
            return;
        }

        Schema::create('airtable_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('auth_type')->default('pat'); // pat (Personal Access Token) or oauth
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token')->nullable(); // For OAuth
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('user_id')->nullable(); // Airtable user ID
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'user_id']);
        });

        // Linked Airtable bases
        if (Schema::hasTable('airtable_bases')) {
            return;
        }

        Schema::create('airtable_bases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('base_id'); // app...
            $table->string('name');
            $table->string('permission_level')->nullable(); // owner, editor, commenter, read
            $table->json('tables')->nullable(); // Cached table list
            $table->timestamp('tables_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('airtable_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'base_id']);
        });

        // Table sync configurations
        if (Schema::hasTable('airtable_table_syncs')) {
            return;
        }

        Schema::create('airtable_table_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('base_id');
            $table->string('table_id'); // tbl...
            $table->string('table_name');
            $table->string('sync_direction'); // push, pull, bidirectional
            $table->string('local_data_type'); // orders, tickets, customers, events
            $table->json('field_mappings'); // local_field => airtable_field
            $table->json('sync_filters')->nullable(); // Conditions for syncing
            $table->boolean('is_auto_sync')->default(false);
            $table->string('sync_frequency')->nullable(); // realtime, hourly, daily
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('base_id')->references('id')->on('airtable_bases')->onDelete('cascade');
            $table->unique(['base_id', 'table_id', 'local_data_type']);
        });

        // Sync job history
        if (Schema::hasTable('airtable_sync_jobs')) {
            return;
        }

        Schema::create('airtable_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_sync_id');
            $table->string('sync_type'); // full, incremental
            $table->string('direction'); // push, pull
            $table->string('status')->default('pending');
            $table->integer('records_processed')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_failed')->default(0);
            $table->json('error_log')->nullable();
            $table->string('triggered_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('table_sync_id')->references('id')->on('airtable_table_syncs')->onDelete('cascade');
        });

        // Record mapping (local ID <=> Airtable record ID)
        if (Schema::hasTable('airtable_record_mappings')) {
            return;
        }

        Schema::create('airtable_record_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('table_sync_id');
            $table->string('local_type'); // orders, tickets, customers
            $table->unsignedBigInteger('local_id');
            $table->string('airtable_record_id'); // rec...
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_hash')->nullable(); // To detect changes
            $table->timestamps();

            $table->foreign('table_sync_id')->references('id')->on('airtable_table_syncs')->onDelete('cascade');
            $table->unique(['table_sync_id', 'local_type', 'local_id'], 'at_rec_map_sync_type_id_unique');
            $table->index(['airtable_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airtable_record_mappings');
        Schema::dropIfExists('airtable_sync_jobs');
        Schema::dropIfExists('airtable_table_syncs');
        Schema::dropIfExists('airtable_bases');
        Schema::dropIfExists('airtable_connections');
    }
};
