<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add color column to ticket_types for seating map visualization
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('description');
        });

        // Create pivot table for ticket type ↔ seating row assignment
        // No unique constraint: multiple ticket types CAN share the same rows
        Schema::create('ticket_type_seating_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->foreignId('seating_row_id')->constrained('seating_rows')->cascadeOnDelete();
            $table->timestamps();

            $table->index('ticket_type_id');
            $table->index('seating_row_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_type_seating_rows');

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
