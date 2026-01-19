<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            // Shop-specific fields
            $table->json('applicable_shop_products')->nullable()->after('applicable_categories');
            $table->json('excluded_shop_products')->nullable()->after('applicable_shop_products');
            $table->json('applicable_shop_categories')->nullable()->after('excluded_shop_products');

            // Free product discount - product UUID to add for free
            $table->uuid('free_product_id')->nullable()->after('applicable_shop_categories');
            $table->uuid('free_product_variant_id')->nullable()->after('free_product_id');
            $table->integer('free_product_quantity')->default(1)->after('free_product_variant_id');

            // Apply to
            $table->json('applies_to')->nullable()->after('free_product_quantity'); // ['tickets', 'shop', 'both']
        });
    }

    public function down(): void
    {
        Schema::table('coupon_codes', function (Blueprint $table) {
            $table->dropColumn([
                'applicable_shop_products',
                'excluded_shop_products',
                'applicable_shop_categories',
                'free_product_id',
                'free_product_variant_id',
                'free_product_quantity',
                'applies_to',
            ]);
        });
    }
};
