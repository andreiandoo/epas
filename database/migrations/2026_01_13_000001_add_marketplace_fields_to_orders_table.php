<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Make tenant_id nullable for marketplace orders
            if (Schema::hasColumn('orders', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            }

            // Marketplace references
            if (!Schema::hasColumn('orders', 'marketplace_client_id')) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                    ->constrained('marketplace_clients')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'marketplace_organizer_id')) {
                $table->foreignId('marketplace_organizer_id')->nullable()->after('marketplace_client_id')
                    ->constrained('marketplace_organizers')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'marketplace_customer_id')) {
                $table->foreignId('marketplace_customer_id')->nullable()->after('marketplace_organizer_id')
                    ->constrained('marketplace_customers')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'marketplace_event_id')) {
                $table->foreignId('marketplace_event_id')->nullable()->after('marketplace_customer_id')
                    ->constrained('marketplace_events')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('marketplace_event_id')
                    ->constrained('events')->nullOnDelete();
            }

            // Order details
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number', 50)->nullable()->unique()->after('event_id');
            }
            if (!Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->default(0)->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('orders', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('orders', 'commission_amount')) {
                $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_rate');
            }
            if (!Schema::hasColumn('orders', 'total')) {
                $table->decimal('total', 10, 2)->default(0)->after('commission_amount');
            }
            if (!Schema::hasColumn('orders', 'currency')) {
                $table->string('currency', 3)->default('RON')->after('total');
            }
            if (!Schema::hasColumn('orders', 'source')) {
                $table->string('source', 50)->default('direct')->after('currency'); // direct, marketplace, widget
            }

            // Customer info (for non-logged-in customers)
            if (!Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name', 255)->nullable()->after('customer_email');
            }
            if (!Schema::hasColumn('orders', 'customer_phone')) {
                $table->string('customer_phone', 50)->nullable()->after('customer_name');
            }

            // Payment info
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 32)->default('pending')->after('status');
            }
            if (!Schema::hasColumn('orders', 'payment_reference')) {
                $table->string('payment_reference', 255)->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'payment_processor')) {
                $table->string('payment_processor', 50)->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('orders', 'payment_error')) {
                $table->text('payment_error')->nullable()->after('payment_processor');
            }

            // Timestamps
            if (!Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('meta');
            }
            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->nullable()->after('refunded_at');
            }
            if (!Schema::hasColumn('orders', 'refund_reason')) {
                $table->text('refund_reason')->nullable()->after('refund_amount');
            }

            // Indexes (only if columns were just added)
            if (!Schema::hasColumn('orders', 'marketplace_client_id')) {
                // Already handled by foreign key constraint
            }
        });

        // Add indexes (ignore errors if they already exist)
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('order_number', 'orders_order_number_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('source', 'orders_source_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('payment_status', 'orders_payment_status_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'marketplace_client_id',
                'marketplace_organizer_id',
                'marketplace_customer_id',
                'marketplace_event_id',
                'event_id',
                'order_number',
                'subtotal',
                'discount_amount',
                'commission_rate',
                'commission_amount',
                'total',
                'currency',
                'source',
                'customer_name',
                'customer_phone',
                'payment_status',
                'payment_reference',
                'payment_processor',
                'payment_error',
                'expires_at',
                'paid_at',
                'cancelled_at',
                'refunded_at',
                'refund_amount',
                'refund_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
