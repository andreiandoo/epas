<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('seating_rows', 'seat_start_number')) {
                $table->integer('seat_start_number')->default(1)->after('label');
            }
            if (!Schema::hasColumn('seating_rows', 'alignment')) {
                $table->string('alignment', 10)->default('left')->after('seat_start_number');
            }
            if (!Schema::hasColumn('seating_rows', 'curve_offset')) {
                $table->decimal('curve_offset', 10, 2)->default(0)->after('alignment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seating_rows', function (Blueprint $table) {
            if (Schema::hasColumn('seating_rows', 'seat_start_number')) {
                $table->dropColumn('seat_start_number');
            }
            if (Schema::hasColumn('seating_rows', 'alignment')) {
                $table->dropColumn('alignment');
            }
            if (Schema::hasColumn('seating_rows', 'curve_offset')) {
                $table->dropColumn('curve_offset');
            }
        });
    }
};
