<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_boats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedInteger('number'); // 1, 2, 3, ..., 25
            $table->string('label', 64)->nullable(); // ex: "Barca albă #5"
            $table->string('qr_code', 64)->unique(); // hash unic generat
            $table->string('status', 16)->default('available'); // available | in_use | maintenance | retired
            $table->timestamps();

            $table->foreign('event_id', 'lboats_event_fk')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('ticket_type_id', 'lboats_tt_fk')->references('id')->on('ticket_types')->onDelete('cascade');

            $table->unique(['ticket_type_id', 'number'], 'lboats_tt_number_unique');
            $table->index(['ticket_type_id', 'status'], 'lboats_tt_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_boats');
    }
};
