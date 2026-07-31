<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in columns on tickets.
 *
 * App\Filament\Operator\Pages\CheckIn already writes scanned_at and
 * scanned_by_user_id, but no migration in the tree creates them — so on any
 * environment that never got them by hand, scanning throws. Additive and
 * guarded, so it is a no-op where they already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'scanned_at')) {
                $table->timestamp('scanned_at')->nullable();
            }
            if (! Schema::hasColumn('tickets', 'scanned_by_user_id')) {
                $table->unsignedBigInteger('scanned_by_user_id')->nullable();
            }
        });

        // Separate pass: the index can only be added once the column exists.
        if (Schema::hasColumn('tickets', 'scanned_at')) {
            Schema::table('tickets', function (Blueprint $table) {
                try {
                    $table->index(['scanned_at'], 'tickets_scanned_at_idx');
                } catch (\Throwable) {
                    // index already present
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }
        Schema::table('tickets', function (Blueprint $table) {
            foreach (['scanned_at', 'scanned_by_user_id'] as $col) {
                if (Schema::hasColumn('tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
