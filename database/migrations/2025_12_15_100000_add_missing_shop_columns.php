<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to shop_categories
        Schema::table('shop_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_categories', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->after('is_active');
            }
            if (!Schema::hasColumn('shop_categories', 'icon')) {
                $table->string('icon')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('shop_categories', 'color')) {
                $table->string('color')->nullable()->after('icon');
            }
            if (!Schema::hasColumn('shop_categories', 'meta_title')) {
                $table->json('meta_title')->nullable()->after('meta');
            }
            if (!Schema::hasColumn('shop_categories', 'meta_description')) {
                $table->json('meta_description')->nullable()->after('meta_title');
            }
        });

        // Add sort_order to shop_shipping_zones
        Schema::table('shop_shipping_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_shipping_zones', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });

        // Change name column to JSON in shop_shipping_zones (for translatable)
        if (Schema::hasColumn('shop_shipping_zones', 'name')) {
            Schema::table('shop_shipping_zones', function (Blueprint $table) {
                $table->json('name')->nullable()->change();
            });
        }

        // Change name and description columns to JSON in shop_shipping_methods (for translatable)
        if (Schema::hasColumn('shop_shipping_methods', 'name')) {
            Schema::table('shop_shipping_methods', function (Blueprint $table) {
                $table->json('name')->nullable()->change();
            });
        }
        if (Schema::hasColumn('shop_shipping_methods', 'description')) {
            Schema::table('shop_shipping_methods', function (Blueprint $table) {
                $table->json('description')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('shop_categories', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'icon', 'color', 'meta_title', 'meta_description']);
        });

        Schema::table('shop_shipping_zones', function (Blueprint $table) {
            $table->dropColumn('sort_order');
            $table->string('name')->change();
        });

        Schema::table('shop_shipping_methods', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('description')->nullable()->change();
        });
    }
};
