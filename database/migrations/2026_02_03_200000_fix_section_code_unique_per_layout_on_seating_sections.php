<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            // Drop any existing unique index on section_code alone (if it exists)
            try {
                $table->dropUnique(['section_code']);
            } catch (\Exception $e) {
                // Index may not exist, that's fine
            }

            // Add composite unique: section_code must be unique within each layout
            $table->unique(['layout_id', 'section_code'], 'seating_sections_layout_section_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropUnique('seating_sections_layout_section_code_unique');
        });
    }
};
