<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_sheets_connections')) {
            return;
        }

        Schema::create('google_sheets_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('google_user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending'); // pending, active, expired, revoked
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'google_user_id']);
        });

        if (Schema::hasTable('google_sheets_spreadsheets')) {
            return;
        }

        Schema::create('google_sheets_spreadsheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('spreadsheet_id');
            $table->string('name');
            $table->string('purpose'); // orders, tickets, customers, events, analytics
            $table->string('web_view_link')->nullable();
            $table->boolean('is_auto_sync')->default(false);
            $table->string('sync_frequency')->nullable(); // hourly, daily, weekly, realtime
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sheet_config')->nullable(); // column mappings, filters, etc.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('google_sheets_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'spreadsheet_id', 'purpose'], 'gs_sheets_conn_sheet_purpose_unique');
        });

        if (Schema::hasTable('google_sheets_sync_jobs')) {
            return;
        }

        Schema::create('google_sheets_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('spreadsheet_id');
            $table->string('sync_type'); // full, incremental, append
            $table->string('data_type'); // orders, tickets, customers, events
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->integer('rows_processed')->default(0);
            $table->integer('rows_created')->default(0);
            $table->integer('rows_updated')->default(0);
            $table->integer('rows_failed')->default(0);
            $table->json('filters')->nullable(); // date range, status, etc.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('error_log')->nullable();
            $table->string('triggered_by')->nullable(); // manual, scheduled, webhook
            $table->timestamps();

            $table->foreign('spreadsheet_id')->references('id')->on('google_sheets_spreadsheets')->onDelete('cascade');
        });

        if (Schema::hasTable('google_sheets_column_mappings')) {
            return;
        }

        Schema::create('google_sheets_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('spreadsheet_id');
            $table->string('data_type'); // orders, tickets, customers
            $table->string('local_field');
            $table->string('sheet_column'); // A, B, C, etc.
            $table->string('column_header');
            $table->string('data_format')->nullable(); // text, number, date, currency
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('spreadsheet_id')->references('id')->on('google_sheets_spreadsheets')->onDelete('cascade');
            $table->unique(['spreadsheet_id', 'data_type', 'local_field'], 'gs_col_map_sheet_type_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheets_column_mappings');
        Schema::dropIfExists('google_sheets_sync_jobs');
        Schema::dropIfExists('google_sheets_spreadsheets');
        Schema::dropIfExists('google_sheets_connections');
    }
};
