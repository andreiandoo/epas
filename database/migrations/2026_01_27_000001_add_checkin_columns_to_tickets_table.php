<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('tickets', 'checked_in_by')) {
                $table->string('checked_in_by', 255)->nullable()->after('checked_in_at');
            }
        });

        // Add index for checked_in_at
        try {
            Schema::table('tickets', function (Blueprint $table) {
                $table->index('checked_in_at', 'tickets_checked_in_at_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'checked_in_at')) {
                $table->dropColumn('checked_in_at');
            }
            if (Schema::hasColumn('tickets', 'checked_in_by')) {
                $table->dropColumn('checked_in_by');
            }
        });
    }
};
