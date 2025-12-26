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
        Schema::create('marketplace_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('api_key', 64)->unique();
            $table->string('api_secret', 128);
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('status')->default('active'); // active, suspended, pending
            $table->decimal('commission_rate', 5, 2)->default(0.00); // % commission on sales
            $table->json('allowed_tenants')->nullable(); // null = all tenants, array = specific tenant IDs
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('api_calls_count')->default(0);
            $table->timestamp('last_api_call_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('api_key');
        });

        // Track which tenants a marketplace client can sell tickets for
        Schema::create('marketplace_client_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->decimal('commission_override', 5, 2)->nullable(); // Override client's default commission
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_client_tenants');
        Schema::dropIfExists('marketplace_clients');
    }
};
