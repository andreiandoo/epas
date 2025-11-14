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
        Schema::create('microservices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('pricing_model')->default('monthly'); // monthly, yearly, one-time, per-use
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable(); // Lista de features
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('microservices');
    }
};
