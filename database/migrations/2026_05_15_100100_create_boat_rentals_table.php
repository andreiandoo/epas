<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_rentals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('boat_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('rental_ticket_id')->nullable(); // biletul de barcă (Ticket.id)
            $table->unsignedBigInteger('access_ticket_id')->nullable(); // biletul acces asociat (Ticket.id)
            $table->unsignedBigInteger('started_by_member_id')->nullable(); // operator care a pornit cursa
            $table->timestamp('started_at');
            $table->timestamp('planned_end_at'); // started_at + (calupuri_planned × calup_duration_minutes)
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedInteger('calup_duration_minutes')->default(30);
            $table->decimal('calup_unit_price', 10, 2)->default(0);
            $table->unsignedInteger('calupuri_planned')->default(1);
            $table->unsignedInteger('calupuri_actual')->default(0);
            $table->decimal('extra_charge_total', 10, 2)->default(0);
            $table->unsignedBigInteger('extra_ticket_id')->nullable(); // bilet calup extra emis
            $table->string('status', 16)->default('active'); // active | ended | finalized | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('event_id', 'brentals_event_fk')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('ticket_type_id', 'brentals_tt_fk')->references('id')->on('ticket_types')->onDelete('cascade');
            $table->foreign('boat_id', 'brentals_boat_fk')->references('id')->on('leisure_boats')->onDelete('restrict');
            $table->foreign('order_id', 'brentals_order_fk')->references('id')->on('orders')->onDelete('set null');

            $table->index(['event_id', 'status'], 'brentals_event_status_idx');
            $table->index(['boat_id', 'status'], 'brentals_boat_status_idx');
            $table->index('rental_ticket_id', 'brentals_ticket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_rentals');
    }
};
