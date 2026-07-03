<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiuni de casa POS (Deschidere / Inchidere) pentru statii de vanzare
 * on-site. La deschidere se creaza un rand cu opened_at; la inchidere se
 * scrie closed_at + un snapshot JSON al incasarilor din intervalul dintre
 * opened_at si closed_at.
 *
 * Un event poate avea mai multe sesiuni (una per zi tipic). O sesiune e
 * legata la un team_member (operatorul care a deschis) sau la organizer
 * (owner) daca nu e team member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->constrained('marketplace_organizers')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            // NULL cand owner-ul face vanzarile direct (fara team member)
            $table->foreignId('team_member_id')->nullable()->constrained('marketplace_organizer_team_members')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('opened_label', 128)->nullable(); // ex: 'InfoPoint' sau numele team member
            // Snapshot incasari (populate la close). Struct:
            //   {totals: {orders,tickets,revenue}, by_payment: [...], by_ticket_type: [...]}
            $table->json('closing_snapshot')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            // Index rapid pentru 'sesiunea deschisa curenta' per organizer+event
            $table->index(['marketplace_organizer_id', 'event_id', 'closed_at'], 'lcs_current_open_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_cashier_sessions');
    }
};
