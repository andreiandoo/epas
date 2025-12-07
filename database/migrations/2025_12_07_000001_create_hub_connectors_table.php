<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Connector catalog - available integrations
        Schema::create('hub_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->string('logo_url')->nullable();
            $table->string('icon')->nullable();
            $table->enum('category', [
                'communication',
                'storage',
                'crm',
                'marketing',
                'automation',
                'analytics',
                'project_management',
                'social',
                'payments',
                'other'
            ])->default('other');
            $table->enum('auth_type', ['oauth2', 'api_key', 'basic', 'webhook_only'])->default('oauth2');
            $table->json('oauth_config')->nullable(); // client_id placeholder, scopes, auth_url, token_url
            $table->json('supported_events')->nullable(); // Events this connector can send/receive
            $table->json('supported_actions')->nullable(); // Actions tenant can trigger
            $table->json('config_schema')->nullable(); // JSON Schema for connector-specific settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->string('documentation_url')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('sort_order');
        });

        // Tenant connections to connectors
        Schema::create('hub_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('connector_id')->constrained('hub_connectors')->onDelete('cascade');
            $table->enum('status', ['pending', 'active', 'error', 'disabled', 'expired'])->default('pending');
            $table->text('credentials')->nullable(); // Encrypted JSON: access_token, refresh_token, api_key
            $table->timestamp('token_expires_at')->nullable();
            $table->json('config')->nullable(); // Tenant-specific configuration
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('error_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['tenant_id', 'connector_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['connector_id', 'status']);
        });

        // Integration events log
        Schema::create('hub_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('event_type', 100); // e.g., "message.created", "contact.updated"
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'retrying'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('hub_connections')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['connection_id', 'event_type']);
            $table->index(['status', 'attempts']);
            $table->index('created_at');
        });

        // Outbound webhook endpoints configured by tenants
        Schema::create('hub_webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('url');
            $table->string('secret', 64); // For signature verification
            $table->json('events')->nullable(); // Subscribed event types
            $table->boolean('is_active')->default(true);
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Sync jobs for data synchronization
        Schema::create('hub_sync_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->enum('job_type', ['full_sync', 'incremental', 'manual'])->default('manual');
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('records_processed')->default(0);
            $table->integer('records_failed')->default(0);
            $table->text('error_log')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('hub_connections')->onDelete('cascade');
            $table->index(['connection_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_sync_jobs');
        Schema::dropIfExists('hub_webhook_endpoints');
        Schema::dropIfExists('hub_events');
        Schema::dropIfExists('hub_connections');
        Schema::dropIfExists('hub_connectors');
    }
};
