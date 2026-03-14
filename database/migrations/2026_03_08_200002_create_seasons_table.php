<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('name')->comment('Translatable season name, e.g. {"ro":"Stagiunea 2025-2026","en":"Season 2025-2026"}');
            $table->string('slug')->index();
            $table->json('description')->nullable()->comment('Translatable description');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 32)->default('planning')->comment('planning|active|completed|archived');
            $table->string('poster_url')->nullable();
            $table->json('settings')->nullable()->comment('Type-specific settings (subscription pricing, etc.)');
            $table->boolean('is_subscription_enabled')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
