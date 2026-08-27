<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice — marketplace-wide days off.
 *
 * On these dates the widget behaves as fully offline (leave-a-message flow)
 * regardless of any operator's weekly schedule.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->date('date');
            $table->string('label', 191)->nullable();

            $table->timestamps();

            $table->unique(['marketplace_client_id', 'date'], 'chat_holiday_date_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_holidays');
    }
};
