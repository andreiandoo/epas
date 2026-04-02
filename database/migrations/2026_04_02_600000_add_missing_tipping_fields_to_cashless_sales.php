<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashless_sales', function (Blueprint $table) {
            $table->decimal('tip_percentage', 5, 2)->nullable()->after('tip_cents');
            $table->integer('total_with_tip_cents')->default(0)->after('tip_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('cashless_sales', function (Blueprint $table) {
            $table->dropColumn(['tip_percentage', 'total_with_tip_cents']);
        });
    }
};
