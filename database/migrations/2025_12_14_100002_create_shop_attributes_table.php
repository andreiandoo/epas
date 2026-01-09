<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_attributes')) {
            return;
        }

        Schema::create('shop_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('name'); // translatable - e.g., "Size", "Color"
            $table->string('slug');
            $table->enum('type', ['select', 'color', 'text'])->default('select');
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
        });

        if (Schema::hasTable('shop_attribute_values')) {
            return;
        }

        Schema::create('shop_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attribute_id');
            $table->json('value'); // translatable - e.g., "Small", "Red"
            $table->string('slug');
            $table->string('color_hex', 7)->nullable(); // for color type attributes
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('attribute_id')->references('id')->on('shop_attributes')->cascadeOnDelete();
            $table->unique(['attribute_id', 'slug']);
            $table->index('attribute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_attribute_values');
        Schema::dropIfExists('shop_attributes');
    }
};
