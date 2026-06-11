<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop any existing unique index on section_code alone (if it exists)
        $indexNames = collect(Schema::getIndexes('seating_sections'))->pluck('name')->toArray();
        if (in_array('seating_sections_section_code_unique', $indexNames)) {
            Schema::table('seating_sections', function (Blueprint $table) {
                $table->dropUnique(['section_code']);
            });
        }

        // Add composite unique: section_code must be unique within each layout
        Schema::table('seating_sections', function (Blueprint $table) {
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
