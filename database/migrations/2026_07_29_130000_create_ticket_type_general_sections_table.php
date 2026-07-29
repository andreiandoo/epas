<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links ticket types to General Access seating sections.
 *
 * Deliberately a SEPARATE pivot from `ticket_type_seating_sections` (which drives
 * the seat-picker `has_seating` flow): GA sections sell via the ticket type's own
 * quota, NOT via seat inventory. Keeping GA links in their own table means the
 * existing seating serialization / checkout paths are byte-for-byte unchanged, so
 * assigning a ticket type to a GA zone can never flip `has_seating` and break its
 * normal quota checkout. Fully additive / non-breaking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_type_general_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->foreignId('seating_section_id')->constrained('seating_sections')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ticket_type_id', 'seating_section_id'], 'tt_ga_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_type_general_sections');
    }
};
