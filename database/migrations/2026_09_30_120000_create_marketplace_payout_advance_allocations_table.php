<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avansuri către organizatori → compensare din deconturile pe eveniment.
 *
 * Un avans e un rând în marketplace_payouts cu source = 'advance' (fără
 * eveniment, finalizat la înregistrare). Când se aprobă ulterior un decont
 * pe eveniment, o parte din avans îl acoperă (FIFO, cel mai vechi avans
 * întâi). Tabelul ține cât din fiecare avans a acoperit fiecare decont,
 * astfel încât soldul să numere avansul o singură dată.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_payout_advance_allocations')) {
            return;
        }

        Schema::create('marketplace_payout_advance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_payout_id')
                ->constrained('marketplace_payouts')
                ->cascadeOnDelete();
            $table->foreignId('payout_id')
                ->constrained('marketplace_payouts')
                ->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['advance_payout_id', 'payout_id'], 'mpaa_advance_payout_unique');
            $table->index('payout_id', 'mpaa_payout_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_payout_advance_allocations');
    }
};
