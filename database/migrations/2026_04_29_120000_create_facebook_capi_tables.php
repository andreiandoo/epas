<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facebook_capi_connections')) {
            Schema::create('facebook_capi_connections', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('marketplace_client_id')->nullable();
                $table->unsignedBigInteger('marketplace_organizer_id')->nullable();

                $table->foreign('tenant_id')
                    ->references('id')->on('tenants')
                    ->nullOnDelete();
                $table->foreign('marketplace_client_id')
                    ->references('id')->on('marketplace_clients')
                    ->nullOnDelete();
                $table->foreign('marketplace_organizer_id')
                    ->references('id')->on('marketplace_organizers')
                    ->nullOnDelete();

                $table->string('pixel_id', 50);
                $table->text('access_token');
                $table->string('business_id')->nullable();
                $table->string('ad_account_id')->nullable();

                $table->boolean('test_mode')->default(false);
                $table->string('test_event_code')->nullable();

                $table->string('status', 20)->default('active');

                $table->json('enabled_events')->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->json('metadata')->nullable();

                $table->softDeletes();
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'fbcapi_conn_tenant_status_idx');
                $table->index(['marketplace_organizer_id', 'status'], 'fbcapi_conn_org_status_idx');
                $table->index(['marketplace_client_id', 'status'], 'fbcapi_conn_mc_status_idx');
            });
        } else {
            Schema::table('facebook_capi_connections', function (Blueprint $table) {
                if (!Schema::hasColumn('facebook_capi_connections', 'marketplace_client_id')) {
                    $table->unsignedBigInteger('marketplace_client_id')->nullable()->after('tenant_id');
                    $table->foreign('marketplace_client_id')
                        ->references('id')->on('marketplace_clients')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('facebook_capi_connections', 'marketplace_organizer_id')) {
                    $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->after('marketplace_client_id');
                    $table->foreign('marketplace_organizer_id')
                        ->references('id')->on('marketplace_organizers')
                        ->nullOnDelete();
                }
            });

            DB::statement('CREATE INDEX IF NOT EXISTS fbcapi_conn_tenant_status_idx ON facebook_capi_connections (tenant_id, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS fbcapi_conn_org_status_idx ON facebook_capi_connections (marketplace_organizer_id, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS fbcapi_conn_mc_status_idx ON facebook_capi_connections (marketplace_client_id, status)');
        }

        if (!Schema::hasTable('facebook_capi_event_configs')) {
            Schema::create('facebook_capi_event_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('connection_id')
                    ->constrained('facebook_capi_connections')
                    ->cascadeOnDelete();
                $table->string('event_name', 50);
                $table->boolean('is_enabled')->default(true);
                $table->string('trigger_on', 50)->nullable();
                $table->json('custom_data_mapping')->nullable();
                $table->json('user_data_mapping')->nullable();
                $table->boolean('send_test_events')->default(false);
                $table->timestamps();

                $table->unique(['connection_id', 'event_name'], 'fbcapi_evcfg_conn_event_uq');
            });
        }

        if (!Schema::hasTable('facebook_capi_events')) {
            Schema::create('facebook_capi_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('connection_id')
                    ->constrained('facebook_capi_connections')
                    ->cascadeOnDelete();
                $table->string('event_id', 100);
                $table->string('event_name', 50);
                $table->timestamp('event_time');
                $table->string('event_source_url', 2048)->nullable();
                $table->string('action_source', 50)->default('website');
                $table->json('user_data')->nullable();
                $table->json('custom_data')->nullable();
                $table->string('correlation_type', 50)->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->string('status', 20)->default('pending');
                $table->string('fbtrace_id')->nullable();
                $table->integer('events_received')->default(0);
                $table->json('messages')->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('is_test_event')->default(false);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['connection_id', 'status'], 'fbcapi_ev_conn_status_idx');
                $table->index('event_id', 'fbcapi_ev_event_id_idx');
                $table->index(['correlation_type', 'correlation_id'], 'fbcapi_ev_corr_idx');
                $table->index(['event_name', 'sent_at'], 'fbcapi_ev_name_sent_idx');
            });
        }

        if (!Schema::hasTable('facebook_capi_batches')) {
            Schema::create('facebook_capi_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('connection_id')
                    ->constrained('facebook_capi_connections')
                    ->cascadeOnDelete();
                $table->integer('event_count');
                $table->string('status', 20)->default('pending');
                $table->integer('events_received')->default(0);
                $table->string('fbtrace_id')->nullable();
                $table->json('messages')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['connection_id', 'status'], 'fbcapi_bt_conn_status_idx');
            });
        }

        if (!Schema::hasTable('facebook_capi_custom_audiences')) {
            Schema::create('facebook_capi_custom_audiences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('connection_id')
                    ->constrained('facebook_capi_connections')
                    ->cascadeOnDelete();
                $table->string('audience_id', 100)->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('subtype', 30)->default('CUSTOM');
                $table->string('data_source', 100)->nullable();
                $table->json('filters')->nullable();
                $table->boolean('is_auto_sync')->default(false);
                $table->integer('approximate_count')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('connection_id', 'fbcapi_aud_conn_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('facebook_capi_connections')) {
            Schema::table('facebook_capi_connections', function (Blueprint $table) {
                if (Schema::hasColumn('facebook_capi_connections', 'marketplace_organizer_id')) {
                    try { $table->dropForeign(['marketplace_organizer_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('marketplace_organizer_id');
                }
                if (Schema::hasColumn('facebook_capi_connections', 'marketplace_client_id')) {
                    try { $table->dropForeign(['marketplace_client_id']); } catch (\Throwable $e) {}
                    $table->dropColumn('marketplace_client_id');
                }
            });

            DB::statement('DROP INDEX IF EXISTS fbcapi_conn_org_status_idx');
            DB::statement('DROP INDEX IF EXISTS fbcapi_conn_mc_status_idx');
        }
    }
};
