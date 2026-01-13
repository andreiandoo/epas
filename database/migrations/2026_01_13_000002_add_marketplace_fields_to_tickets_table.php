<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Marketplace references
            if (!Schema::hasColumn('tickets', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')
                    ->constrained('tenants')->nullOnDelete();
            }
            if (!Schema::hasColumn('tickets', 'marketplace_client_id')) {
                $table->foreignId('marketplace_client_id')->nullable()->after('tenant_id')
                    ->constrained('marketplace_clients')->nullOnDelete();
            }
            if (!Schema::hasColumn('tickets', 'marketplace_customer_id')) {
                $table->foreignId('marketplace_customer_id')->nullable()->after('marketplace_client_id')
                    ->constrained('marketplace_customers')->nullOnDelete();
            }
            if (!Schema::hasColumn('tickets', 'marketplace_event_id')) {
                $table->foreignId('marketplace_event_id')->nullable()->after('marketplace_customer_id')
                    ->constrained('marketplace_events')->nullOnDelete();
            }
            if (!Schema::hasColumn('tickets', 'marketplace_ticket_type_id')) {
                $table->foreignId('marketplace_ticket_type_id')->nullable()->after('marketplace_event_id')
                    ->constrained('marketplace_ticket_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('tickets', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('marketplace_ticket_type_id')
                    ->constrained('events')->nullOnDelete();
            }
            if (!Schema::hasColumn('tickets', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')
                    ->constrained('order_items')->nullOnDelete();
            }

            // Ticket details
            if (!Schema::hasColumn('tickets', 'barcode')) {
                $table->string('barcode', 255)->nullable()->unique()->after('code');
            }
            if (!Schema::hasColumn('tickets', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('status');
            }
            if (!Schema::hasColumn('tickets', 'attendee_name')) {
                $table->string('attendee_name', 255)->nullable()->after('price');
            }
            if (!Schema::hasColumn('tickets', 'attendee_email')) {
                $table->string('attendee_email', 255)->nullable()->after('attendee_name');
            }

        });

        // Add indexes (ignore errors if they already exist)
        try {
            Schema::table('tickets', function (Blueprint $table) {
                $table->index('marketplace_client_id', 'tickets_marketplace_client_id_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
        try {
            Schema::table('tickets', function (Blueprint $table) {
                $table->index('barcode', 'tickets_barcode_idx');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $columns = [
                'tenant_id',
                'marketplace_client_id',
                'marketplace_customer_id',
                'marketplace_event_id',
                'marketplace_ticket_type_id',
                'event_id',
                'order_item_id',
                'barcode',
                'price',
                'attendee_name',
                'attendee_email',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
