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
        if (Schema::hasTable('exchange_rates')) {
            return;
        }

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('base_currency', 3)->default('EUR');
            $table->string('target_currency', 3)->index();
            $table->decimal('rate', 12, 6);
            $table->string('source')->nullable(); // e.g., 'ecb', 'bnr', 'manual'
            $table->timestamps();

            $table->unique(['date', 'base_currency', 'target_currency'], 'unique_rate_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
