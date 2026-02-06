<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add general_stock to events table
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('general_stock')->nullable()->after('target_price');
        });

        // Add sort_order to ticket_types table
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('general_stock');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
