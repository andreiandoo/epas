<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the new price column
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('currency');
        });

        // Convert existing cents to decimal
        DB::statement('UPDATE price_tiers SET price = price_cents / 100');

        // Drop the old column
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->dropColumn('price_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the cents column
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->default(0)->after('currency');
        });

        // Convert decimal to cents
        DB::statement('UPDATE price_tiers SET price_cents = price * 100');

        // Drop the decimal column
        Schema::table('price_tiers', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
