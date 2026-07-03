<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log al incercarilor de scanare (invalid / duplicate) pentru raportare.
 *
 * Scanarile VALIDE sunt deja capturate via Ticket::checked_in_at si
 * LeisureStaffCheckin. Aici retinem doar erorile de scanare — cand
 * app-ul mobil trimite un cod care nu corespunde niciunui bilet valid
 * SAU e un bilet deja folosit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_scan_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->nullable()
                ->constrained('marketplace_organizers')->nullOnDelete();
            $table->string('attempted_code', 128);
            $table->string('result', 32); // 'invalid' | 'duplicate'
            $table->string('reason', 255)->nullable(); // 'not_found' | 'not_yours' | 'cancelled' etc.
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event_id', 'occurred_at'], 'lsa_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_scan_attempts');
    }
};
