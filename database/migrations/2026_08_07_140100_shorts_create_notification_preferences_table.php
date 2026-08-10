<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-type notification opt-outs for behavioural nudges (D12).
 *
 * A row is only written when a customer changes something: absence means "use the
 * default for this type", which keeps the table small and makes a default change
 * apply to everyone who never expressed a preference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_customer_id')
                ->constrained('marketplace_customers')
                ->cascadeOnDelete();
            // shorts_dropped|shorts_trending|followed_posted|shorts_abandoned|...
            $table->string('type', 64);
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(false);
            $table->timestamps();

            $table->unique(['marketplace_customer_id', 'type'], 'notification_preferences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
