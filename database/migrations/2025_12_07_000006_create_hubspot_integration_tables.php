<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hubspot_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('hub_id')->nullable();
            $table->string('hub_domain')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'hub_id']);
        });

        Schema::create('hubspot_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('object_type'); // contacts, deals, companies, tickets
            $table->string('operation');
            $table->string('hubspot_id')->nullable();
            $table->string('local_id')->nullable();
            $table->string('direction')->default('outbound');
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('hubspot_connections')->onDelete('cascade');
            $table->index(['connection_id', 'object_type']);
        });

        Schema::create('hubspot_property_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('object_type');
            $table->string('local_property');
            $table->string('hubspot_property');
            $table->string('direction')->default('bidirectional');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('hubspot_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'object_type', 'local_property']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubspot_property_mappings');
        Schema::dropIfExists('hubspot_sync_logs');
        Schema::dropIfExists('hubspot_connections');
    }
};
