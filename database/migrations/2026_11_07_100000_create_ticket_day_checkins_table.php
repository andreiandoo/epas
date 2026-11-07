<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intrarile la poarta pe zile. tickets.checked_in_at ramane prima intrare;
 * aici stau toate, ca un abonament sa poata intra in fiecare zi.
 * Cheia unica (bilet, zi) opreste doua intrari in aceeasi zi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_day_checkins')) {
            return;
        }

        Schema::create('ticket_day_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->date('day');
            $table->timestamp('checked_in_at');
            $table->string('checked_in_by')->nullable();
            $table->string('checked_in_via', 40)->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_day_checkins');
    }
};
