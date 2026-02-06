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
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'min_per_order')) {
                $table->unsignedInteger('min_per_order')->default(1)->after('quota_sold')
                    ->comment('Minimum tickets required per order');
            }
            if (!Schema::hasColumn('ticket_types', 'max_per_order')) {
                $table->unsignedInteger('max_per_order')->default(10)->after('min_per_order')
                    ->comment('Maximum tickets allowed per order');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'min_per_order')) {
                $table->dropColumn('min_per_order');
            }
            if (Schema::hasColumn('ticket_types', 'max_per_order')) {
                $table->dropColumn('max_per_order');
            }
        });
    }
};
