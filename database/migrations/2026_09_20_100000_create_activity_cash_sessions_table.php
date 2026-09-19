<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module: the cash desk of an operator's location (POS). A session is
 * opened with the cash in the drawer and closed with the cash counted; every POS
 * sale carries its session id in the order meta. New table only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_cash_sessions')) {
            return;
        }
        Schema::create('activity_cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id')->index();
            $table->unsignedBigInteger('marketplace_organizer_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('opened_by', 160)->nullable();
            $table->timestamp('opened_at');
            $table->integer('opening_cash_cents')->default(0);
            $table->string('closed_by', 160)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('counted_cash_cents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['marketplace_organizer_id', 'location_id', 'closed_at'], 'activity_cash_sessions_open_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_cash_sessions');
    }
};
