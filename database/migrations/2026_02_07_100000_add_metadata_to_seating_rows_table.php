<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('seating_rows', 'metadata')) {
                $table->json('metadata')->nullable()->after('seat_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seating_rows', function (Blueprint $table) {
            if (Schema::hasColumn('seating_rows', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
