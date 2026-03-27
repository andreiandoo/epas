<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track which performance was purchased per order item
        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'performance_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedBigInteger('performance_id')->nullable()->after('ticket_type_id');
                $table->foreign('performance_id')->references('id')->on('performances')->nullOnDelete();
            });
        }

        // Seating snapshot per performance (independent seat inventory per show)
        if (Schema::hasTable('event_seating_layouts') && !Schema::hasColumn('event_seating_layouts', 'performance_id')) {
            Schema::table('event_seating_layouts', function (Blueprint $table) {
                $table->unsignedBigInteger('performance_id')->nullable()->after('event_id');
                $table->foreign('performance_id')->references('id')->on('performances')->nullOnDelete();
            });
        }

        // Seat holds bound to specific performance
        if (Schema::hasTable('seat_holds') && !Schema::hasColumn('seat_holds', 'performance_id')) {
            Schema::table('seat_holds', function (Blueprint $table) {
                $table->unsignedBigInteger('performance_id')->nullable()->after('event_seating_id');
                $table->foreign('performance_id')->references('id')->on('performances')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'performance_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['performance_id']);
                $table->dropColumn('performance_id');
            });
        }

        if (Schema::hasTable('event_seating_layouts') && Schema::hasColumn('event_seating_layouts', 'performance_id')) {
            Schema::table('event_seating_layouts', function (Blueprint $table) {
                $table->dropForeign(['performance_id']);
                $table->dropColumn('performance_id');
            });
        }

        if (Schema::hasTable('seat_holds') && Schema::hasColumn('seat_holds', 'performance_id')) {
            Schema::table('seat_holds', function (Blueprint $table) {
                $table->dropForeign(['performance_id']);
                $table->dropColumn('performance_id');
            });
        }
    }
};
