<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix: microservices.price_monthly / price_yearly
        if (!Schema::hasColumn('microservices', 'price_monthly')) {
            Schema::table('microservices', function (Blueprint $table) {
                $table->decimal('price_monthly', 10, 2)->nullable()->after('metadata');
                $table->decimal('price_yearly', 10, 2)->nullable()->after('price_monthly');
            });
        }

        // Fix: gdpr_requests.requested_at
        if (Schema::hasTable('gdpr_requests') && !Schema::hasColumn('gdpr_requests', 'requested_at')) {
            Schema::table('gdpr_requests', function (Blueprint $table) {
                $table->timestamp('requested_at')->nullable()->after('status');
            });
        }

        // Fix: core_customer_events.is_converted
        if (Schema::hasTable('core_customer_events') && !Schema::hasColumn('core_customer_events', 'is_converted')) {
            Schema::table('core_customer_events', function (Blueprint $table) {
                $table->boolean('is_converted')->default(false)->after('os');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('microservices', 'price_monthly')) {
            Schema::table('microservices', function (Blueprint $table) {
                $table->dropColumn(['price_monthly', 'price_yearly']);
            });
        }

        if (Schema::hasTable('gdpr_requests') && Schema::hasColumn('gdpr_requests', 'requested_at')) {
            Schema::table('gdpr_requests', function (Blueprint $table) {
                $table->dropColumn('requested_at');
            });
        }

        if (Schema::hasTable('core_customer_events') && Schema::hasColumn('core_customer_events', 'is_converted')) {
            Schema::table('core_customer_events', function (Blueprint $table) {
                $table->dropColumn('is_converted');
            });
        }
    }
};
