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
        Schema::create('tenant_api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // Descriptive name for the API key
            $table->string('api_key', 64)->unique(); // SHA256 hash of the actual key
            $table->json('scopes')->nullable(); // Array of permission scopes
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active');
            $table->integer('rate_limit')->default(1000); // Requests per hour
            $table->json('allowed_ips')->nullable(); // Optional IP whitelist
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->bigInteger('total_requests')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index('expires_at');
        });

        // Optional: Detailed usage tracking table
        Schema::create('tenant_api_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('api_key_id');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->integer('response_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->timestamp('created_at');

            $table->foreign('api_key_id')->references('id')->on('tenant_api_keys')->onDelete('cascade');
            $table->index(['api_key_id', 'created_at']);
            $table->index('created_at'); // For cleanup queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_api_usage');
        Schema::dropIfExists('tenant_api_keys');
    }
};
