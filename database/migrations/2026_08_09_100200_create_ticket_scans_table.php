<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnalul scanurilor de la poartă.
 *
 * DE CE UN TABEL SEPARAT, si nu doar `tickets.checked_in_at`
 * Coloana de pe bilet retine DOAR prima intrare. Pentru reconciliere avem
 * nevoie de TOATE incercarile, inclusiv duplicatele respinse — altfel nu poti
 * spune organizatorului "biletul asta a fost prezentat la Poarta A la 19:42 si
 * la Poarta B la 19:47".
 *
 * DE CE ATAT `scanned_at` CAT SI `device_at`
 * Prima e ora NORMALIZATA trimisa de aplicatie (ancorata la ora serverului si
 * masurata monoton — vezi src/offline/clock.ts). A doua e ceasul brut al
 * telefonului. Ordonarea se face pe prima; a doua ramane pentru dispute si
 * pentru a depista dispozitive cu ceasul umblat.
 * `clock_trusted` spune daca ancora era din aceeasi rulare a aplicatiei; cand
 * e false, ordonarea e mai slaba si organizatorul trebuie sa stie.
 *
 * `client_scan_id` e generat pe telefon si face primirea IDEMPOTENTA: daca
 * raspunsul se pierde si aplicatia retrimite, nu se numara de doua ori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_scans', function (Blueprint $table) {
            $table->id();

            // idempotenta la retrimitere
            $table->string('client_scan_id', 64)->unique();

            $table->string('ticket_code', 120)->index();
            $table->string('event_id', 40)->index();
            $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->string('device_id', 100)->index();
            $table->string('gate_id', 40)->nullable();

            /** ora normalizata — cu asta se ordoneaza „primul castiga" */
            $table->timestamp('scanned_at')->index();
            /** ceasul brut al telefonului, pentru dispute */
            $table->timestamp('device_at')->nullable();
            $table->unsignedInteger('seq')->default(0);
            $table->integer('skew_ms')->default(0);
            $table->boolean('clock_trusted')->default(false);

            /** ce a decis telefonul pe loc, ca sa vedem unde difera de server */
            $table->string('local_result', 20)->nullable();
            $table->string('result', 20)->index();

            $table->timestamps();

            // interogarea de reconciliere: toate scanurile unui bilet, in ordine
            $table->index(['ticket_code', 'scanned_at'], 'ticket_scans_recon_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_scans');
    }
};
