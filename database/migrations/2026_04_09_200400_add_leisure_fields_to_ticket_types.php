<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'daily_capacity')) {
                $table->integer('daily_capacity')->nullable()->after('ticket_group');
            }
            if (!Schema::hasColumn('ticket_types', 'is_parking')) {
                $table->boolean('is_parking')->default(false)->after('daily_capacity');
            }
            if (!Schema::hasColumn('ticket_types', 'requires_vehicle_info')) {
                $table->boolean('requires_vehicle_info')->default(false)->after('is_parking');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['daily_capacity', 'is_parking', 'requires_vehicle_info']);
        });
    }
};
