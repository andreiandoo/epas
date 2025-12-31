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
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index(); // e.g., 'microservices.whatsapp.enabled'
            $table->string('name'); // Human-readable name
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->enum('rollout_strategy', ['all', 'percentage', 'whitelist', 'custom'])->default('all');
            $table->integer('rollout_percentage')->nullable(); // For percentage rollout (0-100)
            $table->json('whitelist')->nullable(); // Tenant IDs or user IDs for whitelist
            $table->json('conditions')->nullable(); // Custom conditions for evaluation
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
        });

        Schema::create('tenant_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('feature_key')->index(); // References feature_flags.key
            $table->boolean('is_enabled');
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('enabled_by')->nullable(); // User/admin who enabled it
            $table->string('disabled_by')->nullable(); // User/admin who disabled it
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_flags');
        Schema::dropIfExists('feature_flags');
    }
};
