<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need marketplace_client_id support
     */
    protected array $tables = [
        // Blog
        'blog_articles',
        'blog_categories',
        // Shop
        'shop_products',
        'shop_categories',
        'shop_attributes',
        'shop_orders',
        'shop_gift_cards',
        'shop_reviews',
        'shop_shipping_zones',
        // Affiliates
        'affiliates',
        'affiliate_settings',
        'affiliate_withdrawals',
        'affiliate_conversions',
        // Coupons
        'coupon_campaigns',
        'coupon_codes',
        // Gamification
        'gamification_configs',
        'customer_points',
        // Tickets
        'ticket_templates',
        // Group Booking
        'group_bookings',
        // Invitations
        'inv_batches',
        'inv_invites',
        // Tracking/Analytics
        'tracking_integrations',
        // Users (marketplace staff)
        'users',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'marketplace_client_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('marketplace_client_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('marketplace_clients')
                        ->nullOnDelete();

                    $blueprint->index('marketplace_client_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'marketplace_client_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropForeign([$table . '_marketplace_client_id_foreign']);
                    $blueprint->dropColumn('marketplace_client_id');
                });
            }
        }
    }
};
