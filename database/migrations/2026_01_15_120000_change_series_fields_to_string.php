<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change series_start and series_end from unsignedInteger to string
        // to support full ticket codes like 'AMB-5-00001'

        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'series_start')) {
                $table->string('series_start', 50)->nullable()->change();
            }
            if (Schema::hasColumn('marketplace_ticket_types', 'series_end')) {
                $table->string('series_end', 50)->nullable()->change();
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'series_start')) {
                $table->string('series_start', 50)->nullable()->change();
            }
            if (Schema::hasColumn('ticket_types', 'series_end')) {
                $table->string('series_end', 50)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Note: This will fail if there's non-numeric data
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'series_start')) {
                $table->unsignedInteger('series_start')->nullable()->change();
            }
            if (Schema::hasColumn('marketplace_ticket_types', 'series_end')) {
                $table->unsignedInteger('series_end')->nullable()->change();
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'series_start')) {
                $table->unsignedInteger('series_start')->nullable()->change();
            }
            if (Schema::hasColumn('ticket_types', 'series_end')) {
                $table->unsignedInteger('series_end')->nullable()->change();
            }
        });
    }
};
