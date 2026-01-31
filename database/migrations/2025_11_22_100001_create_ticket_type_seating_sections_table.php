<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_type_seating_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->onDelete('cascade');
            $table->foreignId('seating_section_id')->constrained('seating_sections')->onDelete('cascade');
            $table->timestamps();

            // Ensure a section can only be assigned to one ticket type per event
            $table->unique(['ticket_type_id', 'seating_section_id'], 'tt_section_unique');

            // Index for quick lookups
            $table->index('seating_section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_type_seating_sections');
    }
};
