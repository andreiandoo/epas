<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Remind me" for events whose tickets are not on sale yet (D2).
 *
 * remind_at is copied from the ticket type's sales_start_at at creation time
 * rather than resolved at fire time: the reminder must survive the ticket type
 * being edited or removed, and the customer was promised *that* moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('short_reminders')) {
            return;
        }

        Schema::create('short_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_customer_id')
                ->constrained('marketplace_customers')
                ->cascadeOnDelete();
            $table->foreignId('short_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->index();
            $table->foreignId('ticket_type_id')->nullable()->index();
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_customer_id', 'short_id'], 'short_reminders_unique');
            // The scheduled job scans exactly this: due and not yet notified.
            $table->index(['remind_at', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_reminders');
    }
};
