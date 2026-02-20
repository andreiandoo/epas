<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->decimal('fixed_commission_default', 10, 2)->nullable()->after('commission_rate')
                ->comment('Fixed default commission amount (absolute value, not %)');
            $table->text('past_contract')->nullable()->after('company_zip')
                ->comment('Notes or reference to past contract details');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['fixed_commission_default', 'past_contract']);
        });
    }
};
