<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verdictul „soldurile din trecut” pe organizator (Balanțe → coloana Trecut).
 * queued_at = organizatorul așteaptă în coadă o analiză / rezolvare.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_legacy_settlement_verdicts')) {
            return;
        }

        Schema::create('marketplace_legacy_settlement_verdicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_organizer_id')->unique();
            $table->string('verdict', 16)->nullable();
            $table->unsignedInteger('events')->default(0);
            $table->unsignedInteger('payouts')->default(0);
            $table->decimal('settle_amount', 14, 2)->default(0);
            $table->unsignedInteger('issues')->default(0);
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('queued_at')->nullable()->index();
            $table->string('queued_action', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_legacy_settlement_verdicts');
    }
};
