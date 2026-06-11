<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add status field to seating_seats table to support 'imposibil' (permanently unavailable)
     * seats that don't affect numbering but can't be selected by customers.
     */
    public function up(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('seat_uid');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seating_seats', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
