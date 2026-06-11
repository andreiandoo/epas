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
            // Sale stock - limit how many tickets can be sold at sale price
            $table->unsignedInteger('sale_stock')->nullable()->after('sale_price_cents');
            $table->unsignedInteger('sale_stock_sold')->default(0)->after('sale_stock');

            // Active until - when this date is reached, the ticket type is marked as soldout
            $table->timestamp('active_until')->nullable()->after('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['sale_stock', 'sale_stock_sold', 'active_until']);
        });
    }
};
