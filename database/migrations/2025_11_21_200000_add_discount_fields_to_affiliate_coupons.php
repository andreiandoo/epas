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
        Schema::table('affiliate_coupons', function (Blueprint $table) {
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage')->after('coupon_code');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->decimal('min_order_amount', 10, 2)->nullable()->after('discount_value');
            $table->integer('max_uses')->nullable()->after('min_order_amount');
            $table->integer('used_count')->default(0)->after('max_uses');
            $table->timestamp('expires_at')->nullable()->after('used_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_coupons', function (Blueprint $table) {
            $table->dropColumn([
                'discount_type',
                'discount_value',
                'min_order_amount',
                'max_uses',
                'used_count',
                'expires_at',
            ]);
        });
    }
};
