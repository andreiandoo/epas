<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Newer Romanian ID cards can have 7-digit numbers (was varchar 6).
     */
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->string('guarantor_id_number', 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not narrowed back: existing 7-digit values would not fit.
    }
};
