<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert all price columns from cents (integer) to decimal values.
     * Example: 1999 cents -> 19.99 decimal
     */
    public function up(): void
    {
        // shop_products: price_cents, sale_price_cents, cost_cents
        if (Schema::hasTable('shop_products')) {
            $this->convertTablePrices('shop_products', [
                'price_cents' => 'price',
                'sale_price_cents' => 'sale_price',
                'cost_cents' => 'cost',
            ]);
        }

        // shop_product_variants: price_cents, sale_price_cents
        if (Schema::hasTable('shop_product_variants')) {
            $this->convertTablePrices('shop_product_variants', [
                'price_cents' => 'price',
                'sale_price_cents' => 'sale_price',
            ]);
        }

        // shop_shipping_methods: cost_cents, cost_per_kg_cents, min_order_cents, max_order_cents
        if (Schema::hasTable('shop_shipping_methods')) {
            $this->convertTablePrices('shop_shipping_methods', [
                'cost_cents' => 'cost',
                'cost_per_kg_cents' => 'cost_per_kg',
                'min_order_cents' => 'min_order',
                'max_order_cents' => 'max_order',
            ]);
        }

        // shop_orders: subtotal_cents, discount_cents, shipping_cents, tax_cents, total_cents, coupon_discount_cents
        if (Schema::hasTable('shop_orders')) {
            $this->convertTablePrices('shop_orders', [
                'subtotal_cents' => 'subtotal',
                'discount_cents' => 'discount',
                'shipping_cents' => 'shipping',
                'tax_cents' => 'tax',
                'total_cents' => 'total',
                'coupon_discount_cents' => 'coupon_discount',
            ]);
        }

        // shop_order_items: unit_price_cents, total_cents
        if (Schema::hasTable('shop_order_items')) {
            $this->convertTablePrices('shop_order_items', [
                'unit_price_cents' => 'unit_price',
                'total_cents' => 'total',
            ]);
        }

        // shop_carts: subtotal_cents, discount_cents (if exists)
        if (Schema::hasTable('shop_carts')) {
            $columns = [];
            if (Schema::hasColumn('shop_carts', 'subtotal_cents')) {
                $columns['subtotal_cents'] = 'subtotal';
            }
            if (Schema::hasColumn('shop_carts', 'discount_cents')) {
                $columns['discount_cents'] = 'discount';
            }
            if (!empty($columns)) {
                $this->convertTablePrices('shop_carts', $columns);
            }
        }

        // shop_cart_items: unit_price_cents, total_cents (if exists)
        if (Schema::hasTable('shop_cart_items')) {
            $columns = [];
            if (Schema::hasColumn('shop_cart_items', 'unit_price_cents')) {
                $columns['unit_price_cents'] = 'unit_price';
            }
            if (Schema::hasColumn('shop_cart_items', 'total_cents')) {
                $columns['total_cents'] = 'total';
            }
            if (!empty($columns)) {
                $this->convertTablePrices('shop_cart_items', $columns);
            }
        }

        // shop_gift_cards: initial_value_cents, current_value_cents
        if (Schema::hasTable('shop_gift_cards')) {
            $columns = [];
            if (Schema::hasColumn('shop_gift_cards', 'initial_value_cents')) {
                $columns['initial_value_cents'] = 'initial_value';
            }
            if (Schema::hasColumn('shop_gift_cards', 'current_value_cents')) {
                $columns['current_value_cents'] = 'current_value';
            }
            if (!empty($columns)) {
                $this->convertTablePrices('shop_gift_cards', $columns);
            }
        }

        // shop_gift_card_transactions: amount_cents
        if (Schema::hasTable('shop_gift_card_transactions')) {
            if (Schema::hasColumn('shop_gift_card_transactions', 'amount_cents')) {
                $this->convertTablePrices('shop_gift_card_transactions', [
                    'amount_cents' => 'amount',
                ]);
            }
        }

        // shop_wishlists: (no price columns typically)

        // gamification_configs: point_value_cents, min_order_cents_for_earning
        if (Schema::hasTable('gamification_configs')) {
            $this->convertTablePrices('gamification_configs', [
                'point_value_cents' => 'point_value',
                'min_order_cents_for_earning' => 'min_order_for_earning',
            ]);
        }

        // gamification_actions: threshold_cents (if exists)
        if (Schema::hasTable('gamification_actions')) {
            if (Schema::hasColumn('gamification_actions', 'threshold_cents')) {
                $this->convertTablePrices('gamification_actions', [
                    'threshold_cents' => 'threshold',
                ]);
            }
        }
    }

    /**
     * Convert cents columns to decimal for a given table
     */
    private function convertTablePrices(string $table, array $columnMappings): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $columnMappings) {
            foreach ($columnMappings as $oldColumn => $newColumn) {
                if (!Schema::hasColumn($table, $oldColumn)) {
                    continue;
                }

                // Add new decimal column
                $blueprint->decimal($newColumn, 10, 2)->nullable()->after($oldColumn);
            }
        });

        // Copy and convert data
        foreach ($columnMappings as $oldColumn => $newColumn) {
            if (!Schema::hasColumn($table, $oldColumn)) {
                continue;
            }

            DB::statement("UPDATE {$table} SET {$newColumn} = {$oldColumn} / 100.0 WHERE {$oldColumn} IS NOT NULL");
        }

        // Drop old columns
        Schema::table($table, function (Blueprint $blueprint) use ($table, $columnMappings) {
            foreach ($columnMappings as $oldColumn => $newColumn) {
                if (Schema::hasColumn($table, $oldColumn)) {
                    $blueprint->dropColumn($oldColumn);
                }
            }
        });
    }

    public function down(): void
    {
        // Reverse: convert decimal back to cents

        if (Schema::hasTable('shop_products')) {
            $this->revertTablePrices('shop_products', [
                'price' => 'price_cents',
                'sale_price' => 'sale_price_cents',
                'cost' => 'cost_cents',
            ]);
        }

        if (Schema::hasTable('shop_product_variants')) {
            $this->revertTablePrices('shop_product_variants', [
                'price' => 'price_cents',
                'sale_price' => 'sale_price_cents',
            ]);
        }

        if (Schema::hasTable('shop_shipping_methods')) {
            $this->revertTablePrices('shop_shipping_methods', [
                'cost' => 'cost_cents',
                'cost_per_kg' => 'cost_per_kg_cents',
                'min_order' => 'min_order_cents',
                'max_order' => 'max_order_cents',
            ]);
        }

        if (Schema::hasTable('shop_orders')) {
            $this->revertTablePrices('shop_orders', [
                'subtotal' => 'subtotal_cents',
                'discount' => 'discount_cents',
                'shipping' => 'shipping_cents',
                'tax' => 'tax_cents',
                'total' => 'total_cents',
                'coupon_discount' => 'coupon_discount_cents',
            ]);
        }

        if (Schema::hasTable('shop_order_items')) {
            $this->revertTablePrices('shop_order_items', [
                'unit_price' => 'unit_price_cents',
                'total' => 'total_cents',
            ]);
        }

        if (Schema::hasTable('shop_carts')) {
            $columns = [];
            if (Schema::hasColumn('shop_carts', 'subtotal')) {
                $columns['subtotal'] = 'subtotal_cents';
            }
            if (Schema::hasColumn('shop_carts', 'discount')) {
                $columns['discount'] = 'discount_cents';
            }
            if (!empty($columns)) {
                $this->revertTablePrices('shop_carts', $columns);
            }
        }

        if (Schema::hasTable('shop_cart_items')) {
            $columns = [];
            if (Schema::hasColumn('shop_cart_items', 'unit_price')) {
                $columns['unit_price'] = 'unit_price_cents';
            }
            if (Schema::hasColumn('shop_cart_items', 'total')) {
                $columns['total'] = 'total_cents';
            }
            if (!empty($columns)) {
                $this->revertTablePrices('shop_cart_items', $columns);
            }
        }

        if (Schema::hasTable('shop_gift_cards')) {
            $columns = [];
            if (Schema::hasColumn('shop_gift_cards', 'initial_value')) {
                $columns['initial_value'] = 'initial_value_cents';
            }
            if (Schema::hasColumn('shop_gift_cards', 'current_value')) {
                $columns['current_value'] = 'current_value_cents';
            }
            if (!empty($columns)) {
                $this->revertTablePrices('shop_gift_cards', $columns);
            }
        }

        if (Schema::hasTable('shop_gift_card_transactions')) {
            if (Schema::hasColumn('shop_gift_card_transactions', 'amount')) {
                $this->revertTablePrices('shop_gift_card_transactions', [
                    'amount' => 'amount_cents',
                ]);
            }
        }

        if (Schema::hasTable('gamification_configs')) {
            $this->revertTablePrices('gamification_configs', [
                'point_value' => 'point_value_cents',
                'min_order_for_earning' => 'min_order_cents_for_earning',
            ]);
        }

        if (Schema::hasTable('gamification_actions')) {
            if (Schema::hasColumn('gamification_actions', 'threshold')) {
                $this->revertTablePrices('gamification_actions', [
                    'threshold' => 'threshold_cents',
                ]);
            }
        }
    }

    /**
     * Revert decimal columns back to cents
     */
    private function revertTablePrices(string $table, array $columnMappings): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table, $columnMappings) {
            foreach ($columnMappings as $newColumn => $oldColumn) {
                if (!Schema::hasColumn($table, $newColumn)) {
                    continue;
                }

                // Add old integer column
                $blueprint->integer($oldColumn)->nullable()->after($newColumn);
            }
        });

        // Copy and convert data back to cents
        foreach ($columnMappings as $newColumn => $oldColumn) {
            if (!Schema::hasColumn($table, $newColumn)) {
                continue;
            }

            DB::statement("UPDATE {$table} SET {$oldColumn} = ROUND({$newColumn} * 100) WHERE {$newColumn} IS NOT NULL");
        }

        // Drop decimal columns
        Schema::table($table, function (Blueprint $blueprint) use ($table, $columnMappings) {
            foreach ($columnMappings as $newColumn => $oldColumn) {
                if (Schema::hasColumn($table, $newColumn)) {
                    $blueprint->dropColumn($newColumn);
                }
            }
        });
    }
};
