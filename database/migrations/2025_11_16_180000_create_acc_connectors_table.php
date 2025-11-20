<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Accounting Connectors - Provider Configuration
     * Stores authentication and settings per accounting provider
     */
    public function up(): void
    {
        Schema::create('acc_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Provider information
            $table->enum('provider', [
                'smartbill',
                'fgo',
                'exact',
                'xero',
                'quickbooks',
                'mock'
            ])->index();

            // Authentication (encrypted JSON)
            $table->text('auth')->comment('Encrypted authentication credentials (API key, OAuth tokens)');
            // Example for SmartBill: {"api_key": "xxx", "api_secret": "yyy"}
            // Example for OAuth: {"access_token": "xxx", "refresh_token": "yyy", "expires_at": "..."}

            // Status
            $table->enum('status', [
                'pending',      // Not yet authenticated
                'connected',    // Successfully connected
                'error',        // Connection error
                'disabled'      // Manually disabled
            ])->default('pending')->index();

            // Settings (JSON)
            $table->json('settings')->nullable()->comment('Provider-specific settings');
            // Example: {
            //   "issue_extern": true,
            //   "auto_send_invoice": true,
            //   "default_series": "FACT",
            //   "rounding": "round|truncate",
            //   "efactura_by_provider": false,
            //   "language": "ro",
            //   "currency": "RON"
            // }

            // Connection test
            $table->timestamp('last_test_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_connectors');
    }
};
