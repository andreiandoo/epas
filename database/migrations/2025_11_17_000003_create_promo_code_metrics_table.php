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
        Schema::create('promo_code_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id');
            $table->date('date');
            $table->integer('uses')->default(0);
            $table->decimal('total_discount', 10, 2)->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['promo_code_id', 'date']);
            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->onDelete('cascade');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_code_metrics');
    }
};
