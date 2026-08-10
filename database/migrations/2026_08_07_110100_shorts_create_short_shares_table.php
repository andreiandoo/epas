<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Share attribution (D1): one row per share act, counting what came back.
 *
 * Separate from short_events on purpose — a share has a lifecycle (clicks,
 * installs, conversions accrue for days afterwards), while short_events rows
 * are immutable facts and get pruned by retention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('short_shares')) {
            return;
        }

        Schema::create('short_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sharer_customer_id')->nullable()->index(); // MarketplaceCustomer
            $table->string('channel', 32)->nullable();                    // whatsapp|instagram|copy|...
            $table->string('token', 32)->unique();                        // identifies this share in the landing URL
            $table->string('referral_code', 32)->nullable();              // sharer's code, carried into the link
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('installs')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();

            $table->index(['short_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_shares');
    }
};
