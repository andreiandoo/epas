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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // Null for system-level actions
            $table->string('actor_type')->nullable(); // 'user', 'api_key', 'system'
            $table->unsignedBigInteger('actor_id')->nullable(); // User ID or API key ID
            $table->string('actor_name')->nullable(); // Display name
            $table->string('action'); // e.g., 'microservice.activated', 'webhook.created'
            $table->string('resource_type')->nullable(); // e.g., 'microservice', 'webhook', 'api_key'
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('metadata')->nullable(); // Additional context about the action
            $table->json('changes')->nullable(); // Before/after values for updates
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->timestamp('created_at');

            // Indexes for efficient querying
            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at'); // For cleanup queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
