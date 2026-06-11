<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->decimal('commission_rate', 8, 2)->default(10.00)->after('status');
            $table->string('commission_type', 20)->default('percent')->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_type']);
        });
    }
};
