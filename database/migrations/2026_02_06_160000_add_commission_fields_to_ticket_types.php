<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds per-ticket-type commission settings that override organizer/marketplace defaults.
     * - commission_type: null (inherit), 'percentage', 'fixed', or 'both'
     * - commission_rate: percentage rate (when type is 'percentage' or 'both')
     * - commission_fixed: fixed amount per ticket (when type is 'fixed' or 'both')
     * - commission_mode: 'included' (in price) or 'added_on_top' (separate)
     */
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'commission_type')) {
                $table->string('commission_type', 20)->nullable()->after('max_per_order')
                    ->comment('null=inherit, percentage, fixed, or both');
            }
            if (!Schema::hasColumn('ticket_types', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_type')
                    ->comment('Percentage rate (0-100)');
            }
            if (!Schema::hasColumn('ticket_types', 'commission_fixed')) {
                $table->decimal('commission_fixed', 10, 2)->nullable()->after('commission_rate')
                    ->comment('Fixed amount per ticket');
            }
            if (!Schema::hasColumn('ticket_types', 'commission_mode')) {
                $table->string('commission_mode', 20)->nullable()->after('commission_fixed')
                    ->comment('null=inherit, included, or added_on_top');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $columns = ['commission_type', 'commission_rate', 'commission_fixed', 'commission_mode'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('ticket_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
