<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_event_date_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_event_id')->constrained('marketplace_events')->cascadeOnDelete();
            $table->foreignId('marketplace_ticket_type_id')->nullable()->constrained('marketplace_ticket_types')->nullOnDelete();
            $table->date('visit_date');
            $table->integer('capacity');
            $table->integer('sold')->default(0);
            $table->integer('reserved')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->decimal('price_override', 10, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['marketplace_event_id', 'marketplace_ticket_type_id', 'visit_date'], 'medc_event_tt_date_unique');
            $table->index(['marketplace_event_id', 'visit_date'], 'medc_event_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_event_date_capacities');
    }
};
