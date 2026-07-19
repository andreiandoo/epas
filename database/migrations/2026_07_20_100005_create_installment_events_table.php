<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit log for agreement / payment lifecycle. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('installment_events')) {
            return;
        }

        Schema::create('installment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_agreement_id');
            $table->foreignId('installment_payment_id')->nullable();
            $table->string('type'); // created|charged|failed|retried|reminder_sent|defaulted|cancelled|refunded|completed|action_required
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('installment_agreement_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_events');
    }
};
