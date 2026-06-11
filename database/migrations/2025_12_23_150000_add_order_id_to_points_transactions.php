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
        Schema::table('points_transactions', function (Blueprint $table) {
            // Add order_id column if it doesn't exist
            if (!Schema::hasColumn('points_transactions', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('points_transactions', 'order_id')) {
                $table->dropForeign(['order_id']);
                $table->dropColumn('order_id');
            }
        });
    }
};
