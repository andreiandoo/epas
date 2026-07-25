<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_subscription_plans')) {
            Schema::create('tenant_subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('subtitle')->nullable();
                $table->integer('price_cents')->default(0);
                $table->string('currency', 3)->default('RON');
                $table->jsonb('benefits')->nullable();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_plans');
    }
};
