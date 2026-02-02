<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dynamic_pricing_rules')) {
            return;
        }

        Schema::create('dynamic_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->enum('scope', ['event', 'section', 'row', 'seat']);
            $table->string('scope_ref')->nullable(); // Event ID, section name, row label, or seat UID
            $table->enum('strategy', ['time_based', 'velocity', 'threshold', 'custom'])->default('threshold');
            $table->json('params'); // Strategy-specific parameters
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'active']);
            $table->index(['scope', 'scope_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_pricing_rules');
    }
};
