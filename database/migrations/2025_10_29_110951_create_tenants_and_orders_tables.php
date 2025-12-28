<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // TENANTS
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('slug', 190)->unique();
            $table->string('domain', 190)->unique(); // ex: odeon.local
            $table->string('status', 32)->default('active'); // active|suspended|closed
            $table->string('plan', 32)->nullable(); // A|B|C etc
            $table->jsonb('settings')->nullable();  // Postgres jsonb
            $table->timestamps();

            $table->index('status');
            $table->index('plan');
        });

        // ORDERS
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('customer_email', 190);
            $table->bigInteger('total_cents')->default(0);
            $table->string('status', 32)->default('pending'); // pending|paid|cancelled|refunded
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('tenants');
    }
};
