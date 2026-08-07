<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save ("bookmark") state per (short, marketplace customer).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('short_saves')) {
            return;
        }

        Schema::create('short_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_customer_id')
                ->constrained('marketplace_customers')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['short_id', 'marketplace_customer_id'], 'short_saves_unique');
            $table->index(['marketplace_customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_saves');
    }
};
