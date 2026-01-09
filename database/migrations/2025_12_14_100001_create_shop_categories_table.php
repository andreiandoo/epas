<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_categories')) {
            return;
        }

        Schema::create('shop_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->json('name'); // translatable
            $table->string('slug');
            $table->json('description')->nullable(); // translatable
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('shop_categories')->nullOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_categories');
    }
};
