<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salesforce_connections')) {
            return;
        }

        Schema::create('salesforce_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('org_id')->nullable();
            $table->string('instance_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'org_id']);
        });

        if (Schema::hasTable('salesforce_sync_logs')) {
            return;
        }

        Schema::create('salesforce_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('object_type'); // Contact, Lead, Opportunity, Account
            $table->string('operation'); // create, update, delete, sync
            $table->string('salesforce_id')->nullable();
            $table->string('local_id')->nullable();
            $table->string('direction')->default('outbound'); // outbound, inbound
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('salesforce_connections')->onDelete('cascade');
            $table->index(['connection_id', 'object_type']);
        });

        if (Schema::hasTable('salesforce_field_mappings')) {
            return;
        }

        Schema::create('salesforce_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('object_type');
            $table->string('local_field');
            $table->string('salesforce_field');
            $table->string('direction')->default('bidirectional'); // outbound, inbound, bidirectional
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('salesforce_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'object_type', 'local_field'], 'sf_field_map_conn_obj_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesforce_field_mappings');
        Schema::dropIfExists('salesforce_sync_logs');
        Schema::dropIfExists('salesforce_connections');
    }
};
