<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Like state per (short, marketplace customer) — idempotent by unique key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('short_likes')) {
            return;
        }

        Schema::create('short_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_customer_id')
                ->constrained('marketplace_customers')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['short_id', 'marketplace_customer_id'], 'short_likes_unique');
            $table->index(['marketplace_customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_likes');
    }
};
