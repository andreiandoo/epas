<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prețurile vechi ale biletelor corectate de tickets:fix-legacy-prices,
 * ca un lot aplicat să poată fi pus înapoi exact (--revert).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('legacy_ticket_price_fixes')) {
            return;
        }

        Schema::create('legacy_ticket_price_fixes', function (Blueprint $table) {
            $table->id();
            $table->string('batch', 36)->index();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('event_id')->index();
            $table->string('category', 2);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2);
            $table->timestamp('reverted_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_ticket_price_fixes');
    }
};
