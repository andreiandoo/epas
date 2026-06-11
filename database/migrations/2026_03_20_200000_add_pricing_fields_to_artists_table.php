<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('artists', 'min_fee_concert')) {
            Schema::table('artists', function (Blueprint $table) {
                $table->decimal('min_fee_concert', 12, 2)->nullable()->after('booking_agency');
                $table->decimal('max_fee_concert', 12, 2)->nullable()->after('min_fee_concert');
                $table->decimal('min_fee_festival', 12, 2)->nullable()->after('max_fee_concert');
                $table->decimal('max_fee_festival', 12, 2)->nullable()->after('min_fee_festival');
            });
        }
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['min_fee_concert', 'max_fee_concert', 'min_fee_festival', 'max_fee_festival']);
        });
    }
};
