<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Accounting Connectors - Entity Mappings
     * Maps local entities to remote accounting system entities
     */
    public function up(): void
    {
        if (Schema::hasTable('acc_mappings')) {
            return;
        }

        Schema::create('acc_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Entity type being mapped
            $table->enum('entity', [
                'product',          // Product/Service mapping
                'tax',              // Tax/VAT rate mapping
                'account',          // Account code mapping
                'series',           // Invoice series mapping
                'customer_policy'   // Customer creation policy
            ])->index();

            // Local reference (our system)
            $table->string('local_ref')->comment('Local entity identifier');

            // Remote reference (accounting system)
            $table->string('remote_ref')->comment('Remote entity identifier');

            // Additional metadata (JSON)
            $table->json('meta')->nullable()->comment('Additional mapping data');
            // Example for product: {
            //   "local_name": "VIP Ticket",
            //   "remote_name": "Bilet VIP",
            //   "remote_code": "PROD-001",
            //   "unit": "buc",
            //   "account_code": "704"
            // }
            // Example for tax: {
            //   "local_rate": 19,
            //   "remote_id": "vat-19",
            //   "remote_name": "TVA 19%"
            // }

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'entity']);
            $table->index(['tenant_id', 'entity', 'local_ref']);
            $table->unique(['tenant_id', 'entity', 'local_ref'], 'unique_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_mappings');
    }
};
